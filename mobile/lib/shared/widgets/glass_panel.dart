import 'dart:ui';

import 'package:flutter/material.dart';
import 'package:livecommerce_mobile/core/theme/app_dimens.dart';

/// Frosted panel for controls that sit on top of video.
///
/// Blur is deliberately cheap (sigma 12) and this widget should stay rare on a
/// single frame — every instance forces an extra render pass over the video.
class GlassPanel extends StatelessWidget {
  const GlassPanel({
    super.key,
    required this.child,
    this.borderRadius = AppRadius.mdAll,
    this.padding = const EdgeInsets.all(AppSpacing.md),
    this.tint = const Color(0x66101014),
    this.borderColor = const Color(0x26FFFFFF),
    this.blur = 12,
    this.onTap,
  });

  final Widget child;
  final BorderRadius borderRadius;
  final EdgeInsetsGeometry padding;
  final Color tint;
  final Color borderColor;
  final double blur;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return ClipRRect(
      borderRadius: borderRadius,
      child: BackdropFilter(
        filter: ImageFilter.blur(sigmaX: blur, sigmaY: blur),
        child: DecoratedBox(
          decoration: BoxDecoration(
            color: tint,
            borderRadius: borderRadius,
            border: Border.all(color: borderColor),
          ),
          child: Material(
            color: Colors.transparent,
            child: InkWell(
              onTap: onTap,
              borderRadius: borderRadius,
              child: Padding(padding: padding, child: child),
            ),
          ),
        ),
      ),
    );
  }
}

/// Small pill used for LIVE badges, viewer counts and similar metadata chips.
class MediaPill extends StatelessWidget {
  const MediaPill({
    super.key,
    required this.label,
    this.icon,
    this.gradient,
    this.color = const Color(0x8A000000),
    this.dot = false,
  });

  final String label;
  final IconData? icon;
  final Gradient? gradient;
  final Color color;
  final bool dot;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(
        horizontal: AppSpacing.sm + 2,
        vertical: AppSpacing.xs + 1,
      ),
      decoration: BoxDecoration(
        color: gradient == null ? color : null,
        gradient: gradient,
        borderRadius: AppRadius.pillAll,
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (dot) ...[
            Container(
              width: 6,
              height: 6,
              decoration: const BoxDecoration(
                color: Colors.white,
                shape: BoxShape.circle,
              ),
            ),
            const SizedBox(width: AppSpacing.xs + 2),
          ],
          if (icon != null) ...[
            Icon(icon, size: 13, color: Colors.white),
            const SizedBox(width: AppSpacing.xs),
          ],
          Text(
            label,
            style: const TextStyle(
              color: Colors.white,
              fontSize: 11,
              fontWeight: FontWeight.w700,
              letterSpacing: 0.4,
            ),
          ),
        ],
      ),
    );
  }
}
