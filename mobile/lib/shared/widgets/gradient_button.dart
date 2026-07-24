import 'package:flutter/material.dart';
import 'package:livecommerce_mobile/core/theme/app_dimens.dart';

/// Primary call to action rendered with the brand gradient.
///
/// Used for the money-moving actions (buy, checkout, top up) where a flat
/// filled button does not carry enough weight.
class GradientButton extends StatelessWidget {
  const GradientButton({
    super.key,
    required this.label,
    this.onPressed,
    this.icon,
    this.busy = false,
    this.expand = true,
    this.compact = false,
    this.gradient = AppGradients.brand,
  });

  final String label;
  final VoidCallback? onPressed;
  final IconData? icon;
  final bool busy;
  final bool expand;
  final bool compact;
  final Gradient gradient;

  @override
  Widget build(BuildContext context) {
    final enabled = onPressed != null && !busy;
    final height = compact ? 40.0 : 52.0;
    final radius = compact ? AppRadius.pillAll : AppRadius.mdAll;

    final content = busy
        ? const SizedBox(
            height: 20,
            width: 20,
            child: CircularProgressIndicator(
              strokeWidth: 2.2,
              valueColor: AlwaysStoppedAnimation(Colors.white),
            ),
          )
        : Row(
            mainAxisSize: MainAxisSize.min,
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              if (icon != null) ...[
                Icon(icon, size: compact ? 18 : 20, color: Colors.white),
                const SizedBox(width: AppSpacing.sm),
              ],
              Flexible(
                child: Text(
                  label,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.w700,
                    fontSize: compact ? 14 : 16,
                    letterSpacing: 0.1,
                  ),
                ),
              ),
            ],
          );

    return Opacity(
      opacity: enabled ? 1 : 0.5,
      child: DecoratedBox(
        decoration: BoxDecoration(
          gradient: gradient,
          borderRadius: radius,
          boxShadow: enabled ? AppShadows.brandGlow() : null,
        ),
        child: Material(
          color: Colors.transparent,
          child: InkWell(
            onTap: enabled ? onPressed : null,
            borderRadius: radius,
            child: Container(
              height: height,
              width: expand ? double.infinity : null,
              padding: EdgeInsets.symmetric(
                horizontal: compact ? AppSpacing.lg : AppSpacing.xxl,
              ),
              alignment: Alignment.center,
              child: content,
            ),
          ),
        ),
      ),
    );
  }
}
