import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:livecommerce_mobile/core/theme/app_colors.dart';

/// Wraps a video surface and turns a double tap into a like, drawing a heart
/// burst at the tap position.
///
/// A double tap only ever likes — it never unlikes — matching the behaviour
/// users expect from short-video feeds.
class DoubleTapLike extends StatefulWidget {
  const DoubleTapLike({
    super.key,
    required this.child,
    required this.onLike,
    this.onTap,
  });

  final Widget child;
  final VoidCallback onLike;
  final VoidCallback? onTap;

  @override
  State<DoubleTapLike> createState() => _DoubleTapLikeState();
}

class _DoubleTapLikeState extends State<DoubleTapLike>
    with TickerProviderStateMixin {
  final List<_Burst> _bursts = [];
  final _random = math.Random();
  Offset? _lastTapPosition;

  @override
  void dispose() {
    for (final burst in _bursts) {
      burst.controller.dispose();
    }
    super.dispose();
  }

  void _handleDoubleTap() {
    final position = _lastTapPosition;
    if (position == null) {
      return;
    }

    final controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 900),
    );
    final burst = _Burst(
      controller: controller,
      position: position,
      tilt: (_random.nextDouble() - 0.5) * 0.6,
    );

    setState(() => _bursts.add(burst));
    widget.onLike();

    controller.forward().whenComplete(() {
      if (!mounted) {
        controller.dispose();
        return;
      }
      setState(() => _bursts.remove(burst));
      controller.dispose();
    });
  }

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTap: widget.onTap,
      onDoubleTapDown: (details) => _lastTapPosition = details.localPosition,
      onDoubleTap: _handleDoubleTap,
      child: Stack(
        fit: StackFit.expand,
        children: [
          widget.child,
          for (final burst in _bursts) _BurstHeart(burst: burst),
        ],
      ),
    );
  }
}

class _Burst {
  _Burst({
    required this.controller,
    required this.position,
    required this.tilt,
  });

  final AnimationController controller;
  final Offset position;
  final double tilt;
}

class _BurstHeart extends StatelessWidget {
  const _BurstHeart({required this.burst});

  final _Burst burst;

  @override
  Widget build(BuildContext context) {
    const size = 110.0;

    return AnimatedBuilder(
      animation: burst.controller,
      builder: (context, child) {
        final t = burst.controller.value;
        // Pop in fast, hold, then drift up while fading out.
        final scale = t < 0.25
            ? Curves.easeOutBack.transform(t / 0.25) * 1.1
            : 1.1 - (t - 0.25) * 0.15;
        final opacity = t < 0.55 ? 1.0 : 1.0 - ((t - 0.55) / 0.45);
        final rise = t < 0.4 ? 0.0 : (t - 0.4) * 90;

        return Positioned(
          left: burst.position.dx - size / 2,
          top: burst.position.dy - size / 2 - rise,
          child: Opacity(
            opacity: opacity.clamp(0.0, 1.0),
            child: Transform.rotate(
              angle: burst.tilt,
              child: Transform.scale(scale: scale.clamp(0.0, 1.3), child: child),
            ),
          ),
        );
      },
      child: const Icon(
        Icons.favorite_rounded,
        size: size,
        color: AppColors.brandPink,
        shadows: [
          Shadow(color: Color(0x66FF3366), blurRadius: 24),
          Shadow(color: Color(0x40000000), blurRadius: 12, offset: Offset(0, 4)),
        ],
      ),
    );
  }
}
