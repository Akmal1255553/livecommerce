import 'dart:io' show Platform;

import 'package:agora_rtc_engine/agora_rtc_engine.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:livecommerce_mobile/core/l10n/app_localizations.dart';
import 'package:livecommerce_mobile/features/live/domain/entities/live_session.dart';
import 'package:permission_handler/permission_handler.dart';

Widget buildLiveAvSurface({
  required LiveSession session,
  required bool isHost,
}) {
  final supported = !kIsWeb && (Platform.isAndroid || Platform.isIOS);
  if (!supported) {
    return const _NativeOnlyBanner();
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
  String? _error;

  @override
  void initState() {
    super.initState();
    _bootstrap();
  }

  Future<void> _bootstrap() async {
    try {
      if (widget.isHost) {
        await [Permission.microphone, Permission.camera].request();
      } else {
        await Permission.microphone.request();
      }

      final engine = createAgoraRtcEngine();
      await engine.initialize(RtcEngineContext(
        appId: widget.appId,
        channelProfile: ChannelProfileType.channelProfileLiveBroadcasting,
      ));

      engine.registerEventHandler(RtcEngineEventHandler(
        onJoinChannelSuccess: (connection, elapsed) {
          if (mounted) {
            setState(() => _joined = true);
          }
        },
        onUserJoined: (connection, remoteUid, elapsed) {
          if (mounted) {
            setState(() => _remoteUid = remoteUid);
          }
        },
        onUserOffline: (connection, remoteUid, reason) {
          if (mounted && _remoteUid == remoteUid) {
            setState(() => _remoteUid = null);
          }
        },
        onError: (err, msg) {
          if (mounted) {
            setState(() => _error = '$err');
          }
        },
      ));

      await engine.enableVideo();
      await engine.setClientRole(
        role: widget.isHost
            ? ClientRoleType.clientRoleBroadcaster
            : ClientRoleType.clientRoleAudience,
      );

      if (widget.isHost) {
        await engine.startPreview();
      }

      await engine.joinChannel(
        token: widget.token,
        channelId: widget.channelId,
        uid: 0,
        options: ChannelMediaOptions(
          autoSubscribeAudio: true,
          autoSubscribeVideo: true,
          publishCameraTrack: widget.isHost,
          publishMicrophoneTrack: widget.isHost,
          clientRoleType: widget.isHost
              ? ClientRoleType.clientRoleBroadcaster
              : ClientRoleType.clientRoleAudience,
        ),
      );

      if (!mounted) {
        await engine.leaveChannel();
        await engine.release();
        return;
      }
      setState(() => _engine = engine);
    } catch (e) {
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
      engine.leaveChannel();
      engine.release();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (_error != null) {
      return ColoredBox(
        color: const Color(0xFF1A1A2E),
        child: Center(
          child: Text(
            _error!,
            style: const TextStyle(color: Colors.white70),
            textAlign: TextAlign.center,
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
