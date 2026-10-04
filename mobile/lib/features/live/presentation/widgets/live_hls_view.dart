import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/foundation.dart';
import 'package:livecommerce_mobile/core/video/browser_media_surface.dart';
import 'package:livecommerce_mobile/core/l10n/app_localizations.dart';
import 'package:video_player/video_player.dart';

/// Plays an HLS live stream (MediaMTX output) for RTMP-based sessions.
///
/// The playlist may not be ready yet when a viewer joins — retry every few
/// seconds until the relay starts serving the stream.
class LiveHlsView extends StatefulWidget {
  const LiveHlsView({super.key, required this.hlsUrl});

  final String hlsUrl;

  @override
  State<LiveHlsView> createState() => _LiveHlsViewState();
}

class _LiveHlsViewState extends State<LiveHlsView> {
  VideoPlayerController? _controller;
  Timer? _retryTimer;
  bool _failed = false;

  @override
  void initState() {
    super.initState();
    if (!kIsWeb) _connect();
  }

  @override
  void dispose() {
    _retryTimer?.cancel();
    _controller?.dispose();
    super.dispose();
  }

  Future<void> _connect() async {
    final controller = VideoPlayerController.networkUrl(
      Uri.parse(widget.hlsUrl),
    );
    try {
      await controller.initialize();
      await controller.setLooping(true);
      await controller.play();
      if (!mounted) {
        controller.dispose();
        return;
      }
      setState(() {
        _controller = controller;
        _failed = false;
      });
    } catch (_) {
      controller.dispose();
      if (!mounted) {
        return;
      }
      setState(() => _failed = true);
      _retryTimer?.cancel();
      _retryTimer = Timer(const Duration(seconds: 5), _connect);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (kIsWeb) {
      return BrowserMediaSurface(configuration: {
        'mode': 'video', 'url': widget.hlsUrl, 'live': true, 'controls': true,
      });
    }
    final controller = _controller;
    if (controller != null && controller.value.isInitialized) {
      return VideoPlayer(controller);
    }

    return ColoredBox(
      color: const Color(0xFF1A1A2E),
      child: Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const CircularProgressIndicator(color: Colors.white54),
            const SizedBox(height: 12),
            Text(
              _failed
                  ? AppLocalizations.of(context)!.liveHlsWaiting
                  : AppLocalizations.of(context)!.liveConnecting,
              style: const TextStyle(color: Colors.white70, fontSize: 14),
            ),
          ],
        ),
      ),
    );
  }
}
