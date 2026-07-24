import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:livecommerce_mobile/core/theme/app_colors.dart';
import 'package:livecommerce_mobile/core/theme/app_dimens.dart';
import 'package:livecommerce_mobile/core/theme/app_typography.dart';
import 'package:livecommerce_mobile/core/utils/number_format.dart';
import 'package:livecommerce_mobile/features/feed/domain/entities/product_card.dart';
import 'package:livecommerce_mobile/shared/widgets/app_cached_image.dart';
import 'package:livecommerce_mobile/shared/widgets/glass_panel.dart';

/// Shoppable product card pinned over a video or live stream.
class ProductOverlayChip extends StatelessWidget {
  const ProductOverlayChip({
    super.key,
    required this.product,
    this.actionLabel,
  });

  final ProductCard product;
  final String? actionLabel;

  @override
  Widget build(BuildContext context) {
    void open() => context.push('/products/${product.id}');

    return GlassPanel(
      onTap: open,
      borderRadius: AppRadius.lgAll,
      padding: const EdgeInsets.all(AppSpacing.sm + 2),
      child: Row(
        children: [
          ClipRRect(
            borderRadius: AppRadius.smAll,
            child: product.thumbnail != null
                ? AppCachedImage(
                    url: product.thumbnail!,
                    width: 52,
                    height: 52,
                    fit: BoxFit.cover,
                    memCacheWidth: 156,
                    errorWidget: const _ThumbPlaceholder(),
                  )
                : const _ThumbPlaceholder(),
          ),
          const SizedBox(width: AppSpacing.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  product.title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: AppColors.onMedia,
                    fontWeight: FontWeight.w600,
                    fontSize: 14,
                    height: 1.2,
                  ),
                ),
                const SizedBox(height: AppSpacing.xxs),
                Text(
                  formatPrice(product.price, product.currency),
                  style: AppTypography.numeric(
                    fontSize: 16,
                    color: AppColors.onMedia,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: AppSpacing.sm),
          _BuyPill(label: actionLabel, onTap: open),
        ],
      ),
    );
  }
}

class _BuyPill extends StatelessWidget {
  const _BuyPill({required this.label, required this.onTap});

  final String? label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: BoxDecoration(
        gradient: AppGradients.brand,
        borderRadius: AppRadius.pillAll,
        boxShadow: AppShadows.brandGlow(opacity: 0.45),
      ),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: onTap,
          borderRadius: AppRadius.pillAll,
          child: Padding(
            padding: const EdgeInsets.symmetric(
              horizontal: AppSpacing.lg,
              vertical: AppSpacing.sm + 2,
            ),
            child: Text(
              label ?? 'Buy',
              style: const TextStyle(
                color: Colors.white,
                fontWeight: FontWeight.w700,
                fontSize: 14,
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _ThumbPlaceholder extends StatelessWidget {
  const _ThumbPlaceholder();

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 52,
      height: 52,
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.16),
        borderRadius: AppRadius.smAll,
      ),
      child: const Icon(
        Icons.shopping_bag_outlined,
        color: AppColors.onMediaMuted,
        size: 24,
      ),
    );
  }
}
