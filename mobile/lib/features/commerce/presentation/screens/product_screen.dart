import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:livecommerce_mobile/features/commerce/domain/entities/product_detail.dart';
import 'package:livecommerce_mobile/features/commerce/presentation/providers/commerce_providers.dart';

class ProductScreen extends ConsumerStatefulWidget {
  const ProductScreen({super.key, required this.productId});

  final String productId;

  @override
  ConsumerState<ProductScreen> createState() => _ProductScreenState();
}

class _ProductScreenState extends ConsumerState<ProductScreen> {
  String? _selectedVariantId;
  int _quantity = 1;

  @override
  Widget build(BuildContext context) {
    final productAsync = ref.watch(productDetailProvider(widget.productId));
    final cart = ref.watch(cartNotifierProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Product'),
        actions: [
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
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text(error.toString(), textAlign: TextAlign.center),
                const SizedBox(height: 16),
                FilledButton(
                  onPressed: () => ref.invalidate(productDetailProvider(widget.productId)),
                  child: const Text('Retry'),
                ),
              ],
            ),
          ),
        ),
        data: (product) => _ProductBody(
          product: product,
          selectedVariantId: _selectedVariantId,
          quantity: _quantity,
          isAdding: cart.isMutating,
          onVariantSelected: (id) => setState(() => _selectedVariantId = id),
          onQuantityChanged: (q) => setState(() => _quantity = q),
          onAddToCart: () => _addToCart(product),
        ),
      ),
    );
  }

  Future<void> _addToCart(ProductDetail product) async {
    if (product.variants.isNotEmpty && _selectedVariantId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please select a variant')),
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
          content: const Text('Added to cart'),
          action: SnackBarAction(
            label: 'View cart',
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
    required this.onVariantSelected,
    required this.onQuantityChanged,
    required this.onAddToCart,
  });

  final ProductDetail product;
  final String? selectedVariantId;
  final int quantity;
  final bool isAdding;
  final ValueChanged<String> onVariantSelected;
  final ValueChanged<int> onQuantityChanged;
  final VoidCallback onAddToCart;

  @override
  Widget build(BuildContext context) {
    final priceText = NumberFormat('#,###').format(product.price.toInt());
    final theme = Theme.of(context);

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
                      ? Image.network(
                          product.primaryImageUrl!,
                          fit: BoxFit.cover,
                          width: double.infinity,
                          errorBuilder: (_, __, ___) => const ColoredBox(
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
                        Text('Variants', style: theme.textTheme.titleMedium),
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
                          Text('Quantity', style: theme.textTheme.titleMedium),
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
            child: SizedBox(
              width: double.infinity,
              child: FilledButton(
                onPressed: product.isPurchasable && !isAdding ? onAddToCart : null,
                child: isAdding
                    ? const SizedBox(
                        height: 20,
                        width: 20,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : Text(product.isPurchasable ? 'Add to cart' : 'Out of stock'),
              ),
            ),
          ),
        ),
      ],
    );
  }
}
