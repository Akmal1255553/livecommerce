import 'package:flutter/material.dart';
import 'package:livecommerce_mobile/shared/widgets/app_cached_image.dart';
import 'package:video_player/video_player.dart';

/// TikTok-style muted-by-default HLS/MP4 player for one feed page.
///
/// Plays only while [isActive]; pauses when off-screen. Tap toggles mute.
/// Pass a [prefetchedController] from [FeedVideoPrefetch.claim] to skip init.
class FeedVideoPlayer extends StatefulWidget {
  const FeedVideoPlayer({
    super.key,
    required this.videoUrl,
    this.thumbnailUrl,
    required this.isActive,
    this.prefetchedController,
  });

  final String videoUrl;
  final String? thumbnailUrl;
  final bool isActive;

  /// Ownership transfers to this widget; disposed with the player.
  final VideoPlayerController? prefetchedController;

  @override
  State<FeedVideoPlayer> createState() => _FeedVideoPlayerState();
}

class _FeedVideoPlayerState extends State<FeedVideoPlayer>
    with WidgetsBindingObserver {
  VideoPlayerController? _controller;
  bool _muted = true;
  bool _hasError = false;
  bool _initializing = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _adoptOrInit();
  }

  @override
  void didUpdateWidget(covariant FeedVideoPlayer oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.videoUrl != widget.videoUrl) {
      _disposeController();
      _adoptOrInit();
      return;
    }
    if (widget.prefetchedController != null &&
        widget.prefetchedController != oldWidget.prefetchedController &&
        _controller == null) {
      _adoptOrInit();
      return;
    }
    _syncPlayback();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    final controller = _controller;
    if (controller == null || !controller.value.isInitialized) {
      return;
    }
    if (state == AppLifecycleState.inactive ||
        state == AppLifecycleState.paused ||
        state == AppLifecycleState.detached) {
      controller.pause();
    } else if (state == AppLifecycleState.resumed && widget.isActive) {
      controller.play();
    }
  }

  void _adoptOrInit() {
    final prefetched = widget.prefetchedController;
    if (prefetched != null && prefetched.value.isInitialized) {
      _controller = prefetched;
      _hasError = false;
      _initializing = false;
      _syncPlayback();
      return;
    }
    _initController();
  }

  Future<void> _initController() async {
    if (_initializing) {
      return;
    }
    setState(() {
      _hasError = false;
      _initializing = true;
    });

    final controller = VideoPlayerController.networkUrl(
      Uri.parse(widget.videoUrl),
      videoPlayerOptions: VideoPlayerOptions(mixWithOthers: true),
    );

    try {
      await controller.initialize();
      await controller.setLooping(true);
      await controller.setVolume(_muted ? 0 : 1);
      if (!mounted) {
        await controller.dispose();
        return;
      }
      setState(() {
        _controller = controller;
        _initializing = false;
      });
      _syncPlayback();
    } catch (_) {
      await controller.dispose();
      if (!mounted) {
        return;
      }
      setState(() {
        _controller = null;
        _hasError = true;
        _initializing = false;
      });
    }
  }

  void _syncPlayback() {
    final controller = _controller;
    if (controller == null || !controller.value.isInitialized) {
      return;
    }
    if (widget.isActive) {
      controller.setVolume(_muted ? 0 : 1);
      controller.play();
    } else {
      controller.pause();
    }
  }

  Future<void> _toggleMute() async {
    final controller = _controller;
    if (controller == null || !controller.value.isInitialized) {
      return;
    }
    final nextMuted = !_muted;
    await controller.setVolume(nextMuted ? 0 : 1);
    if (mounted) {
      setState(() => _muted = nextMuted);
    }
  }

  Future<void> _disposeController() async {
    final controller = _controller;
    _controller = null;
    if (controller != null) {
      await controller.dispose();
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    final controller = _controller;
    _controller = null;
    controller?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final controller = _controller;
    final ready = controller != null && controller.value.isInitialized;

    return GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTap: ready ? _toggleMute : null,
      child: Stack(
        fit: StackFit.expand,
        children: [
          if (widget.thumbnailUrl != null)
            AppCachedImage(
              url: widget.thumbnailUrl!,
              fit: BoxFit.cover,
              memCacheWidth: 720,
            )
          else
            const ColoredBox(color: Color(0xFF1A1A1A)),
          if (ready)
            FittedBox(
              fit: BoxFit.cover,
              clipBehavior: Clip.hardEdge,
              child: SizedBox(
                width: controller.value.size.width,
                height: controller.value.size.height,
                child: VideoPlayer(controller),
              ),
            ),
          if (_initializing && !ready)
            const Center(
              child: CircularProgressIndicator(color: Colors.white54),
            ),
          if (_hasError)
            const Align(
              alignment: Alignment.topCenter,
              child: Padding(
                padding: EdgeInsets.only(top: 72),
                child: Chip(
                  backgroundColor: Colors.black54,
                  label: Text(
                    'Playback unavailable',
                    style: TextStyle(color: Colors.white70, fontSize: 12),
                  ),
                ),
              ),
            ),
          if (ready)
            Positioned(
              right: 16,
              bottom: 220,
              child: Icon(
                _muted ? Icons.volume_off : Icons.volume_up,
                color: Colors.white70,
                size: 22,
              ),
            ),
        ],
      ),
    );
  }
}

/// Thumbnail-only fallback when [videoUrl] is missing.
class FeedThumbnailFallback extends StatelessWidget {
  const FeedThumbnailFallback({super.key, this.thumbnailUrl});

  final String? thumbnailUrl;

  @override
  Widget build(BuildContext context) {
    if (thumbnailUrl != null) {
      return AppCachedImage(
        url: thumbnailUrl!,
        fit: BoxFit.cover,
        memCacheWidth: 720,
      );
    }
    return const ColoredBox(color: Color(0xFF1A1A1A));
  }
}
