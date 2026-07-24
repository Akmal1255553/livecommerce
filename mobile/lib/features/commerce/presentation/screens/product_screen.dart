import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:livecommerce_mobile/core/l10n/app_localizations.dart';
import 'package:livecommerce_mobile/core/theme/app_colors.dart';
import 'package:livecommerce_mobile/core/theme/app_dimens.dart';
import 'package:livecommerce_mobile/core/utils/number_format.dart';
import 'package:livecommerce_mobile/features/commerce/domain/entities/product_detail.dart';
import 'package:livecommerce_mobile/features/commerce/presentation/providers/commerce_providers.dart';
import 'package:livecommerce_mobile/features/messaging/presentation/providers/messaging_providers.dart';
import 'package:livecommerce_mobile/features/moderation/presentation/report_sheet.dart';
import 'package:livecommerce_mobile/shared/widgets/app_cached_image.dart';
import 'package:livecommerce_mobile/shared/widgets/error_widget.dart';
import 'package:livecommerce_mobile/shared/widgets/gradient_button.dart';
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
    final palette = AppPalette.of(context);
    final theme = Theme.of(context);
    final l10n = AppLocalizations.of(context)!;
    final priceText = formatPrice(product.price, product.currency);

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
                          placeholderColor: palette.surfaceHigh,
                          errorWidget: ColoredBox(
                            color: palette.surfaceHigh,
                            child: Icon(
                              Icons.image_not_supported_outlined,
                              size: 64,
                              color: palette.textTertiary,
                            ),
                          ),
                        )
                      : ColoredBox(
                          color: palette.surfaceHigh,
                          child: Center(
                            child: Icon(
                              Icons.shopping_bag_outlined,
                              size: 64,
                              color: palette.textTertiary,
                            ),
                          ),
                        ),
                ),
                Padding(
                  padding: const EdgeInsets.all(AppSpacing.lg),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      if (product.storeName != null)
                        Text(
                          product.storeName!,
                          style: theme.textTheme.labelLarge?.copyWith(
                            color: palette.brand,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      const SizedBox(height: AppSpacing.sm),
                      Text(
                        product.title,
                        style: theme.textTheme.headlineSmall?.copyWith(
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      const SizedBox(height: AppSpacing.sm),
                      Text(
                        priceText,
                        style: theme.textTheme.titleLarge?.copyWith(
                          color: palette.brand,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      if (product.description != null &&
                          product.description!.isNotEmpty) ...[
                        const SizedBox(height: AppSpacing.lg),
                        Text(
                          product.description!,
                          style: theme.textTheme.bodyMedium?.copyWith(
                            color: palette.textSecondary,
                            height: 1.45,
                          ),
                        ),
                      ],
                      if (product.variants.isNotEmpty) ...[
                        const SizedBox(height: AppSpacing.xl),
                        Text(l10n.variants, style: theme.textTheme.titleMedium),
                        const SizedBox(height: AppSpacing.sm),
                        Wrap(
                          spacing: AppSpacing.sm,
                          runSpacing: AppSpacing.sm,
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
                      const SizedBox(height: AppSpacing.xl),
                      Row(
                        children: [
                          Text(l10n.quantity, style: theme.textTheme.titleMedium),
                          const Spacer(),
                          _QtyButton(
                            icon: Icons.remove_rounded,
                            onTap: quantity > 1
                                ? () => onQuantityChanged(quantity - 1)
                                : null,
                          ),
                          Padding(
                            padding: const EdgeInsets.symmetric(
                              horizontal: AppSpacing.md,
                            ),
                            child: Text(
                              '$quantity',
                              style: theme.textTheme.titleMedium?.copyWith(
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ),
                          _QtyButton(
                            icon: Icons.add_rounded,
                            onTap: () => onQuantityChanged(quantity + 1),
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
        Container(
          decoration: BoxDecoration(
            color: palette.surface,
            border: Border(top: BorderSide(color: palette.outline)),
          ),
          child: SafeArea(
            child: Padding(
              padding: const EdgeInsets.all(AppSpacing.lg),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  GradientButton(
                    label: product.isPurchasable
                        ? l10n.addToCart
                        : l10n.outOfStock,
                    icon: Icons.shopping_bag_outlined,
                    busy: isAdding,
                    onPressed: product.isPurchasable && !isAdding
                        ? onAddToCart
                        : null,
                  ),
                  if (onMessageSeller != null) ...[
                    const SizedBox(height: AppSpacing.sm),
                    OutlinedButton.icon(
                      onPressed: isMessaging ? null : onMessageSeller,
                      icon: isMessaging
                          ? const SizedBox(
                              height: 16,
                              width: 16,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : const Icon(Icons.chat_bubble_outline_rounded),
                      label: Text(l10n.messageSeller),
                    ),
                  ],
                ],
              ),
            ),
          ),
        ),
      ],
    );
  }
}

class _QtyButton extends StatelessWidget {
  const _QtyButton({required this.icon, this.onTap});

  final IconData icon;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final palette = AppPalette.of(context);

    return Material(
      color: palette.surfaceHigh,
      borderRadius: AppRadius.pillAll,
      child: InkWell(
        onTap: onTap,
        borderRadius: AppRadius.pillAll,
        child: SizedBox(
          width: 36,
          height: 36,
          child: Icon(
            icon,
            size: 18,
            color: onTap == null ? palette.textTertiary : palette.textPrimary,
          ),
        ),
      ),
    );
  }
}
