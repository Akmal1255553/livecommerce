import 'dart:async';
import 'dart:io' show Platform;

import 'package:agora_rtc_engine/agora_rtc_engine.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:livecommerce_mobile/core/l10n/app_localizations.dart';
import 'package:livecommerce_mobile/features/live/domain/entities/live_session.dart';
import 'package:livecommerce_mobile/features/live/presentation/widgets/live_hls_view.dart';
import 'package:livecommerce_mobile/features/live/presentation/widgets/live_obs_panel.dart';
import 'package:permission_handler/permission_handler.dart';

Widget buildLiveAvSurface({
  required LiveSession session,
  required bool isHost,
}) {
  final supported = !kIsWeb && (Platform.isAndroid || Platform.isIOS);
  if (!supported) {
    return const _NativeOnlyBanner();
  }

  // RTMP (OBS) sessions: hosts push from OBS, viewers play the HLS relay.
  if (session.isRtmp && session.hlsUrl != null) {
    return isHost
        ? LiveObsHostPanel(session: session)
        : LiveHlsView(hlsUrl: session.hlsUrl!);
  }

  final appId = session.appId;
  final channelId = session.channelId;
  final token = isHost ? session.publisherToken : session.subscriberToken;

  if (appId == null ||
      appId.isEmpty ||
      channelId == null ||
      channelId.isEmpty ||
      token == null ||
      token.isEmpty) {
    return const _NativeOnlyBanner(missingCreds: true);
  }

  return _AgoraLiveView(
    key: ValueKey('$appId:$channelId:$isHost'),
    appId: appId,
    channelId: channelId,
    token: token,
    isHost: isHost,
  );
}

class _NativeOnlyBanner extends StatelessWidget {
  const _NativeOnlyBanner({this.missingCreds = false});

  final bool missingCreds;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    return Container(
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [Color(0xFF1A1A2E), Color(0xFF16213E), Color(0xFF0F3460)],
        ),
      ),
      child: Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.videocam, color: Colors.white54, size: 64),
              const SizedBox(height: 12),
              Text(
                missingCreds
                    ? l10n.livePlaceholderTitle
                    : l10n.liveAvNativeOnly,
                style: const TextStyle(color: Colors.white70, fontSize: 16),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 4),
              Text(
                l10n.livePlaceholderBody,
                style: const TextStyle(color: Colors.white38, fontSize: 13),
                textAlign: TextAlign.center,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _AgoraLiveView extends StatefulWidget {
  const _AgoraLiveView({
    super.key,
    required this.appId,
    required this.channelId,
    required this.token,
    required this.isHost,
  });

  final String appId;
  final String channelId;
  final String token;
  final bool isHost;

  @override
  State<_AgoraLiveView> createState() => _AgoraLiveViewState();
}

class _AgoraLiveViewState extends State<_AgoraLiveView> {
  RtcEngine? _engine;
  int? _remoteUid;
  bool _joined = false;
  bool _permissionDenied = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _bootstrap();
  }

  @override
  void didUpdateWidget(covariant _AgoraLiveView oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (_joined && oldWidget.token != widget.token) {
      unawaited(_renewToken());
    }
  }

  Future<void> _renewToken() async {
    final engine = _engine;
    if (engine == null) return;
    try {
      await engine.renewToken(widget.token);
    } catch (_) {
      if (mounted && identical(_engine, engine)) setState(() => _error = 'Не удалось продлить подключение. Подключитесь повторно.');
    }
  }

  Future<void> _release(RtcEngine engine) async {
    try { await engine.leaveChannel(); } catch (_) {}
    try { await engine.release(); } catch (_) {}
  }

  Future<void> _retry() async {
    final previous = _engine;
    _engine = null;
    setState(() { _error = null; _joined = false; _remoteUid = null; _permissionDenied = false; });
    if (previous != null) await _release(previous);
    if (mounted) await _bootstrap();
  }

  Future<void> _bootstrap() async {
    RtcEngine? initializingEngine;
    try {
      if (widget.isHost) {
        final permissions = await [Permission.microphone, Permission.camera].request();
        if (!mounted) return;
        if (permissions[Permission.microphone] != PermissionStatus.granted ||
            permissions[Permission.camera] != PermissionStatus.granted) {
          setState(() {
            _permissionDenied = true;
            _error = 'Разрешите камеру и микрофон в настройках приложения, затем повторите подключение.';
          });
          return;
        }
      }

      final engine = createAgoraRtcEngine();
      initializingEngine = engine;
      await engine.initialize(RtcEngineContext(
        appId: widget.appId,
        channelProfile: ChannelProfileType.channelProfileLiveBroadcasting,
      ));
      if (!mounted) { await _release(engine); return; }
      _engine = engine;

      engine.registerEventHandler(RtcEngineEventHandler(
        onJoinChannelSuccess: (connection, elapsed) {
          if (mounted && identical(_engine, engine)) {
            setState(() => _joined = true);
          }
        },
        onUserJoined: (connection, remoteUid, elapsed) {
          if (mounted && identical(_engine, engine)) {
            setState(() => _remoteUid = remoteUid);
          }
        },
        onUserOffline: (connection, remoteUid, reason) {
          if (mounted && identical(_engine, engine) && _remoteUid == remoteUid) {
            setState(() => _remoteUid = null);
          }
        },
        onError: (err, msg) {
          if (mounted && identical(_engine, engine)) {
            setState(() => _error = '$err');
          }
        },
      ));

      await engine.enableVideo();
      await engine.enableAudio();
      await engine.enableLocalVideo(widget.isHost);
      await engine.enableLocalAudio(widget.isHost);
      await engine.setDefaultAudioRouteToSpeakerphone(true);
      await engine.setClientRole(
        role: widget.isHost
            ? ClientRoleType.clientRoleBroadcaster
            : ClientRoleType.clientRoleAudience,
      );

      if (widget.isHost) {
        await engine.setVideoEncoderConfiguration(const VideoEncoderConfiguration(
          dimensions: VideoDimensions(width: 640, height: 480), frameRate: 15,
        ));
        await engine.startPreview();
      }

      await engine.joinChannel(
        token: widget.token,
        channelId: widget.channelId,
        uid: 0,
        options: ChannelMediaOptions(
          autoSubscribeAudio: true,
          autoSubscribeVideo: true,
          enableAudioRecordingOrPlayout: true,
          publishCameraTrack: widget.isHost,
          publishMicrophoneTrack: widget.isHost,
          clientRoleType: widget.isHost
              ? ClientRoleType.clientRoleBroadcaster
              : ClientRoleType.clientRoleAudience,
        ),
      );

      if (!mounted) {
        await _release(engine);
        return;
      }
      setState(() => _engine = engine);
    } catch (e) {
      if (identical(_engine, initializingEngine)) _engine = null;
      if (initializingEngine != null) await _release(initializingEngine);
      if (mounted) {
        setState(() => _error = e.toString());
      }
    }
  }

  @override
  void dispose() {
    final engine = _engine;
    _engine = null;
    if (engine != null) {
      unawaited(_release(engine));
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (_error != null) {
      return ColoredBox(
        color: const Color(0xFF1A1A2E),
        child: Center(
          child: Padding(
            padding: const EdgeInsets.all(20),
            child: Column(mainAxisSize: MainAxisSize.min, children: [
              Text(_error!, style: const TextStyle(color: Colors.white70), textAlign: TextAlign.center),
              const SizedBox(height: 12),
              FilledButton(onPressed: _retry, child: const Text('Повторить подключение')),
              if (_permissionDenied)
                const TextButton(onPressed: openAppSettings, child: Text('Открыть настройки')),
            ]),
          ),
        ),
      );
    }

    final engine = _engine;
    if (engine == null) {
      return const ColoredBox(
        color: Color(0xFF1A1A2E),
        child: Center(child: CircularProgressIndicator(color: Colors.white54)),
      );
    }

    if (widget.isHost) {
      return AgoraVideoView(
        controller: VideoViewController(
          rtcEngine: engine,
          canvas: const VideoCanvas(uid: 0),
        ),
      );
    }

    if (_remoteUid != null) {
      return AgoraVideoView(
        controller: VideoViewController.remote(
          rtcEngine: engine,
          canvas: VideoCanvas(uid: _remoteUid),
          connection: RtcConnection(channelId: widget.channelId),
        ),
      );
    }

    return ColoredBox(
      color: const Color(0xFF1A1A2E),
      child: Center(
        child: Text(
          _joined
              ? AppLocalizations.of(context)!.liveWaitingHost
              : AppLocalizations.of(context)!.liveConnecting,
          style: const TextStyle(color: Colors.white70),
        ),
      ),
    );
  }
}
