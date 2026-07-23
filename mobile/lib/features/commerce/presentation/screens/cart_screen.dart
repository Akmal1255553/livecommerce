import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:livecommerce_mobile/core/l10n/app_localizations.dart';
import 'package:livecommerce_mobile/features/commerce/domain/entities/cart.dart';
import 'package:livecommerce_mobile/features/commerce/presentation/providers/commerce_providers.dart';
import 'package:livecommerce_mobile/shared/widgets/empty_state.dart';
import 'package:livecommerce_mobile/shared/widgets/error_widget.dart';
import 'package:livecommerce_mobile/shared/widgets/app_cached_image.dart';
import 'package:livecommerce_mobile/shared/widgets/skeleton.dart';

class CartScreen extends ConsumerStatefulWidget {
  const CartScreen({super.key});

  @override
  ConsumerState<CartScreen> createState() => _CartScreenState();
}

class _CartScreenState extends ConsumerState<CartScreen> {
  @override
  void initState() {
    super.initState();
    Future.microtask(() => ref.read(cartNotifierProvider.notifier).load());
  }

  @override
  Widget build(BuildContext context) {
    final cartState = ref.watch(cartNotifierProvider);

    return Scaffold(
      appBar: AppBar(title: Text(AppLocalizations.of(context)!.cartTitle)),
      body: _buildBody(context, cartState),
      bottomNavigationBar: cartState.cart != null && !cartState.cart!.isEmpty
          ? _CartSummaryBar(cart: cartState.cart!)
          : null,
    );
  }

  Widget _buildBody(BuildContext context, CartState cartState) {
    if (cartState.isLoading && cartState.cart == null) {
      return const CartSkeleton();
    }

    if (cartState.error != null && (cartState.cart == null || cartState.cart!.isEmpty)) {
      return ErrorDisplay(
        message: cartState.error!,
        onRetry: () => ref.read(cartNotifierProvider.notifier).load(),
      );
    }

    final cart = cartState.cart;
    if (cart == null || cart.isEmpty) {
      final l10n = AppLocalizations.of(context)!;
      return EmptyState(
        title: l10n.cartEmptyTitle,
        subtitle: l10n.cartEmptySubtitle,
        icon: Icons.shopping_cart_outlined,
        actionLabel: l10n.browseFeed,
        onAction: () => context.go('/home'),
      );
    }

    return RefreshIndicator(
      onRefresh: () => ref.read(cartNotifierProvider.notifier).load(),
      child: ListView.separated(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(16),
        itemCount: cart.items.length,
        separatorBuilder: (_, __) => const Divider(),
        itemBuilder: (context, index) {
          final item = cart.items[index];
          return _CartLineTile(
            item: item,
            isMutating: cartState.isMutating,
            onDecrease: () => ref
                .read(cartNotifierProvider.notifier)
                .updateQuantity(item.id, item.quantity - 1),
            onIncrease: () => ref
                .read(cartNotifierProvider.notifier)
                .updateQuantity(item.id, item.quantity + 1),
            onRemove: () =>
                ref.read(cartNotifierProvider.notifier).removeItem(item.id),
          );
        },
      ),
    );
  }
}

class _CartLineTile extends StatelessWidget {
  const _CartLineTile({
    required this.item,
    required this.isMutating,
    required this.onDecrease,
    required this.onIncrease,
    required this.onRemove,
  });

  final CartLineItem item;
  final bool isMutating;
  final VoidCallback onDecrease;
  final VoidCallback onIncrease;
  final VoidCallback onRemove;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (item.product.thumbnail != null)
          AppCachedImage(
            url: item.product.thumbnail!,
            width: 72,
            height: 72,
            fit: BoxFit.cover,
            memCacheWidth: 144,
            borderRadius: BorderRadius.circular(8),
          )
        else
          Container(
            width: 72,
            height: 72,
            decoration: BoxDecoration(
              color: theme.colorScheme.surfaceContainerHighest,
              borderRadius: BorderRadius.circular(8),
            ),
            child: const Icon(Icons.shopping_bag_outlined),
          ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                item.product.title,
                style: theme.textTheme.titleSmall,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
              ),
              if (item.variant != null)
                Text(
                  '${item.variant!.name}: ${item.variant!.value}',
                  style: theme.textTheme.bodySmall,
                ),
              const SizedBox(height: 4),
              Text(
                item.lineTotal.format(),
                style: theme.textTheme.titleMedium?.copyWith(
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 8),
              Row(
                children: [
                  IconButton(
                    onPressed: isMutating ? null : onDecrease,
                    icon: const Icon(Icons.remove_circle_outline),
                  ),
                  Text('${item.quantity}'),
                  IconButton(
                    onPressed: isMutating ? null : onIncrease,
                    icon: const Icon(Icons.add_circle_outline),
                  ),
                  const Spacer(),
                  TextButton(
                    onPressed: isMutating ? null : onRemove,
                    child: Text(AppLocalizations.of(context)!.remove),
                  ),
                ],
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _CartSummaryBar extends StatelessWidget {
  const _CartSummaryBar({required this.cart});

  final Cart cart;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(l10n.subtotal),
                Text(
                  cart.summary.subtotal.format(),
                  style: const TextStyle(fontWeight: FontWeight.w700),
                ),
              ],
            ),
            const SizedBox(height: 12),
            FilledButton(
              onPressed: () => context.push('/checkout'),
              child: Text(l10n.checkout),
            ),
          ],
        ),
      ),
    );
  }
}
