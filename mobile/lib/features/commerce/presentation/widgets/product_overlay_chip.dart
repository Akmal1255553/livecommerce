import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:livecommerce_mobile/features/feed/domain/entities/product_card.dart';
import 'package:livecommerce_mobile/shared/widgets/app_cached_image.dart';

class ProductOverlayChip extends StatelessWidget {
  const ProductOverlayChip({
    super.key,
    required this.product,
  });

  final ProductCard product;

  @override
  Widget build(BuildContext context) {
    final priceText = NumberFormat('#,###').format(product.price.toInt());

    return Material(
      color: Colors.black54,
      borderRadius: BorderRadius.circular(12),
      child: InkWell(
        onTap: () => context.push('/products/${product.id}'),
        borderRadius: BorderRadius.circular(12),
        child: Padding(
          padding: const EdgeInsets.all(10),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (product.thumbnail != null)
                AppCachedImage(
                  url: product.thumbnail!,
                  width: 48,
                  height: 48,
                  fit: BoxFit.cover,
                  memCacheWidth: 96,
                  borderRadius: BorderRadius.circular(8),
                  errorWidget: const _ThumbPlaceholder(),
                )
              else
                const _ThumbPlaceholder(),
              const SizedBox(width: 10),
              Flexible(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      product.title,
                      style: const TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.w600,
                        fontSize: 13,
                      ),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                    const SizedBox(height: 2),
                    Text(
                      '$priceText ${product.currency}',
                      style: const TextStyle(
                        color: Color(0xFFFF6B6B),
                        fontWeight: FontWeight.w700,
                        fontSize: 14,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 6),
              const Icon(Icons.chevron_right, color: Colors.white70, size: 20),
            ],
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
      width: 48,
      height: 48,
      decoration: BoxDecoration(
        color: Colors.white24,
        borderRadius: BorderRadius.circular(8),
      ),
      child: const Icon(Icons.shopping_bag_outlined, color: Colors.white54, size: 24),
    );
  }
}
