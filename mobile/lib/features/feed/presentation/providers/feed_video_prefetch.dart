import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:livecommerce_mobile/features/feed/domain/entities/feed_video.dart';
import 'package:video_player/video_player.dart';

/// Warms the next feed video controller so swipe-up starts faster.
class FeedVideoPrefetch {
  VideoPlayerController? _controller;
  String? _url;
  Future<void>? _inflight;

  String? get warmedUrl => _url;

  /// Prefetch [url] if not already warming / warmed.
  Future<void> warm(String? url) async {
    if (url == null || url.isEmpty) {
      return;
    }
    if (_url == url && (_controller != null || _inflight != null)) {
      return;
    }

    await _disposeHeld();
    _url = url;
    final work = _initialize(url);
    _inflight = work;
    try {
      await work;
    } finally {
      if (identical(_inflight, work)) {
        _inflight = null;
      }
    }
  }

  Future<void> _initialize(String url) async {
    final controller = VideoPlayerController.networkUrl(
      Uri.parse(url),
      videoPlayerOptions: VideoPlayerOptions(mixWithOthers: true),
    );
    try {
      await controller.initialize();
      await controller.setLooping(true);
      await controller.setVolume(0);
      if (_url != url) {
        await controller.dispose();
        return;
      }
      _controller = controller;
    } catch (error, stack) {
      debugPrint('FeedVideoPrefetch failed for $url: $error\n$stack');
      await controller.dispose();
      if (_url == url) {
        _url = null;
        _controller = null;
      }
    }
  }

  /// Transfers ownership of a ready controller for [url], or null.
  VideoPlayerController? claim(String url) {
    if (_url != url || _controller == null) {
      return null;
    }
    final controller = _controller;
    _controller = null;
    _url = null;
    return controller;
  }

  /// Prefetch the next item after [currentIndex] that has a playable URL.
  Future<void> warmNext(List<FeedVideo> videos, int currentIndex) async {
    for (var i = currentIndex + 1; i < videos.length; i++) {
      final next = videos[i].videoUrl;
      if (next != null && next.isNotEmpty) {
        await warm(next);
        return;
      }
    }
  }

  Future<void> _disposeHeld() async {
    final pending = _inflight;
    if (pending != null) {
      try {
        await pending;
      } catch (_) {}
    }
    final controller = _controller;
    _controller = null;
    _url = null;
    await controller?.dispose();
  }

  Future<void> dispose() => _disposeHeld();
}

final feedVideoPrefetchProvider = Provider<FeedVideoPrefetch>((ref) {
  final prefetch = FeedVideoPrefetch();
  ref.onDispose(prefetch.dispose);
  return prefetch;
});
