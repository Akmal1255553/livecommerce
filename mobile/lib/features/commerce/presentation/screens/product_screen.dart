import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:livecommerce_mobile/core/l10n/app_localizations.dart';
import 'package:livecommerce_mobile/features/commerce/domain/entities/product_detail.dart';
import 'package:livecommerce_mobile/features/commerce/presentation/providers/commerce_providers.dart';
import 'package:livecommerce_mobile/features/messaging/presentation/providers/messaging_providers.dart';
import 'package:livecommerce_mobile/features/moderation/presentation/report_sheet.dart';
import 'package:livecommerce_mobile/shared/widgets/app_cached_image.dart';
import 'package:livecommerce_mobile/shared/widgets/error_widget.dart';
import 'package:livecommerce_mobile/shared/widgets/skeleton.dart';

class ProductScreen extends ConsumerStatefulWidget {
  const ProductScreen({super.key, required this.productId});

  final String productId;

  @override
  ConsumerState<ProductScreen> createState() => _ProductScreenState();
}

class _ProductScreenState extends ConsumerState<ProductScreen> {
  String? _selectedVariantId;
  int _quantity = 1;
  bool _isMessaging = false;

  @override
  Widget build(BuildContext context) {
    final productAsync = ref.watch(productDetailProvider(widget.productId));
    final cart = ref.watch(cartNotifierProvider);

    return Scaffold(
      appBar: AppBar(
        title: Text(AppLocalizations.of(context)!.productTitle),
        actions: [
          IconButton(
            tooltip: AppLocalizations.of(context)!.reportAction,
            icon: const Icon(Icons.flag_outlined),
            onPressed: () => showReportSheet(
              context: context,
              ref: ref,
              targetType: 'product',
              targetId: widget.productId,
            ),
          ),
          IconButton(
            icon: Badge(
              isLabelVisible: cart.itemCount > 0,
              label: Text('${cart.itemCount}'),
              child: const Icon(Icons.shopping_cart_outlined),
            ),
            onPressed: () => context.push('/cart'),
          ),
        ],
      ),
      body: productAsync.when(
        loading: () => const Padding(
          padding: EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              SkeletonBox(width: double.infinity, height: 220, borderRadius: 16),
              SizedBox(height: 20),
              SkeletonBox(width: 240, height: 20),
              SizedBox(height: 12),
              SkeletonBox(width: 120, height: 16),
              SizedBox(height: 12),
              SkeletonBox(width: double.infinity, height: 60),
            ],
          ),
        ),
        error: (error, _) => ErrorDisplay(
          message: error.toString(),
          onRetry: () =>
              ref.invalidate(productDetailProvider(widget.productId)),
        ),
        data: (product) => _ProductBody(
          product: product,
          selectedVariantId: _selectedVariantId,
          quantity: _quantity,
          isAdding: cart.isMutating,
          isMessaging: _isMessaging,
          onVariantSelected: (id) => setState(() => _selectedVariantId = id),
          onQuantityChanged: (q) => setState(() => _quantity = q),
          onAddToCart: () => _addToCart(product),
          onMessageSeller: product.sellerUserId != null
              ? () => _messageSeller(product)
              : null,
        ),
      ),
    );
  }

  Future<void> _messageSeller(ProductDetail product) async {
    if (product.sellerUserId == null) return;
    setState(() => _isMessaging = true);
    try {
      final conv = await ref
          .read(conversationsNotifierProvider.notifier)
          .createConversation(sellerId: product.sellerUserId!);
      if (mounted) {
        context.push('/conversations/${conv.id}', extra: conv);
      }
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content:
                Text(AppLocalizations.of(context)!.sendMessageFailed),
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _isMessaging = false);
    }
  }

  Future<void> _addToCart(ProductDetail product) async {
    if (product.variants.isNotEmpty && _selectedVariantId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(AppLocalizations.of(context)!.selectVariant)),
      );
      return;
    }

    final success = await ref.read(cartNotifierProvider.notifier).addProduct(
          productId: product.id,
          variantId: _selectedVariantId,
          quantity: _quantity,
        );

    if (!mounted) {
      return;
    }

    if (success) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(AppLocalizations.of(context)!.addedToCart),
          action: SnackBarAction(
            label: AppLocalizations.of(context)!.viewCart,
            onPressed: () => context.push('/cart'),
          ),
        ),
      );
    } else {
      final error = ref.read(cartNotifierProvider).error;
      if (error != null) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error)));
      }
    }
  }
}

class _ProductBody extends StatelessWidget {
  const _ProductBody({
    required this.product,
    required this.selectedVariantId,
    required this.quantity,
    required this.isAdding,
    required this.isMessaging,
    required this.onVariantSelected,
    required this.onQuantityChanged,
    required this.onAddToCart,
    this.onMessageSeller,
  });

  final ProductDetail product;
  final String? selectedVariantId;
  final int quantity;
  final bool isAdding;
  final bool isMessaging;
  final ValueChanged<String> onVariantSelected;
  final ValueChanged<int> onQuantityChanged;
  final VoidCallback onAddToCart;
  final VoidCallback? onMessageSeller;

  @override
  Widget build(BuildContext context) {
    final priceText = NumberFormat('#,###').format(product.price.toInt());
    final theme = Theme.of(context);
    final l10n = AppLocalizations.of(context)!;

    return Column(
      children: [
        Expanded(
          child: SingleChildScrollView(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                AspectRatio(
                  aspectRatio: 1,
                  child: product.primaryImageUrl != null
                      ? AppCachedImage(
                          url: product.primaryImageUrl!,
                          fit: BoxFit.cover,
                          width: double.infinity,
                          memCacheWidth: 900,
                          placeholderColor: const Color(0xFFF0F0F0),
                          errorWidget: const ColoredBox(
                            color: Color(0xFFF0F0F0),
                            child: Icon(Icons.image_not_supported_outlined, size: 64),
                          ),
                        )
                      : const ColoredBox(
                          color: Color(0xFFF0F0F0),
                          child: Center(
                            child: Icon(Icons.shopping_bag_outlined, size: 64),
                          ),
                        ),
                ),
                Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      if (product.storeName != null)
                        Text(
                          product.storeName!,
                          style: theme.textTheme.labelLarge?.copyWith(
                            color: theme.colorScheme.primary,
                          ),
                        ),
                      const SizedBox(height: 8),
                      Text(
                        product.title,
                        style: theme.textTheme.headlineSmall?.copyWith(
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        '$priceText ${product.currency}',
                        style: theme.textTheme.titleLarge?.copyWith(
                          color: theme.colorScheme.error,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      if (product.description != null &&
                          product.description!.isNotEmpty) ...[
                        const SizedBox(height: 16),
                        Text(
                          product.description!,
                          style: theme.textTheme.bodyMedium,
                        ),
                      ],
                      if (product.variants.isNotEmpty) ...[
                        const SizedBox(height: 20),
                        Text(l10n.variants, style: theme.textTheme.titleMedium),
                        const SizedBox(height: 8),
                        Wrap(
                          spacing: 8,
                          runSpacing: 8,
                          children: product.variants.map((variant) {
                            final selected = selectedVariantId == variant.id;
                            return ChoiceChip(
                              label: Text('${variant.name}: ${variant.value}'),
                              selected: selected,
                              onSelected: (_) => onVariantSelected(variant.id),
                            );
                          }).toList(),
                        ),
                      ],
                      const SizedBox(height: 20),
                      Row(
                        children: [
                          Text(l10n.quantity, style: theme.textTheme.titleMedium),
                          const Spacer(),
                          IconButton(
                            onPressed: quantity > 1
                                ? () => onQuantityChanged(quantity - 1)
                                : null,
                            icon: const Icon(Icons.remove_circle_outline),
                          ),
                          Text('$quantity', style: theme.textTheme.titleMedium),
                          IconButton(
                            onPressed: () => onQuantityChanged(quantity + 1),
                            icon: const Icon(Icons.add_circle_outline),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
        SafeArea(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                FilledButton(
                  onPressed: product.isPurchasable && !isAdding ? onAddToCart : null,
                  child: isAdding
                      ? const SizedBox(
                          height: 20,
                          width: 20,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : Text(
                          product.isPurchasable ? l10n.addToCart : l10n.outOfStock,
                        ),
                ),
                if (onMessageSeller != null) ...[
                  const SizedBox(height: 8),
                  OutlinedButton(
                    onPressed: isMessaging ? null : onMessageSeller,
                    child: isMessaging
                        ? const SizedBox(
                            height: 18,
                            width: 18,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : Text(l10n.messageSeller),
                  ),
                ],
              ],
            ),
          ),
        ),
      ],
    );
  }
}
