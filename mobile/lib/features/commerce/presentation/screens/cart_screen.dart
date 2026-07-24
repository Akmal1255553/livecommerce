import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:livecommerce_mobile/core/l10n/app_localizations.dart';
import 'package:livecommerce_mobile/core/theme/app_colors.dart';
import 'package:livecommerce_mobile/core/theme/app_dimens.dart';
import 'package:livecommerce_mobile/features/commerce/domain/entities/cart.dart';
import 'package:livecommerce_mobile/features/commerce/presentation/providers/commerce_providers.dart';
import 'package:livecommerce_mobile/shared/widgets/app_cached_image.dart';
import 'package:livecommerce_mobile/shared/widgets/empty_state.dart';
import 'package:livecommerce_mobile/shared/widgets/error_widget.dart';
import 'package:livecommerce_mobile/shared/widgets/gradient_button.dart';
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
    final l10n = AppLocalizations.of(context)!;
    final cart = cartState.cart;

    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.cartTitle),
        bottom: cart != null && !cart.isEmpty
            ? PreferredSize(
                preferredSize: const Size.fromHeight(24),
                child: Padding(
                  padding: const EdgeInsets.only(
                    left: AppSpacing.lg,
                    bottom: AppSpacing.sm,
                  ),
                  child: Align(
                    alignment: Alignment.centerLeft,
                    child: Text(
                      l10n.itemCountLabel(cart.summary.itemCount),
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                            color: AppPalette.of(context).textTertiary,
                          ),
                    ),
                  ),
                ),
              )
            : null,
      ),
      body: _buildBody(context, cartState),
      bottomNavigationBar:
          cart != null && !cart.isEmpty ? _CartSummaryBar(cart: cart) : null,
    );
  }

  Widget _buildBody(BuildContext context, CartState cartState) {
    if (cartState.isLoading && cartState.cart == null) {
      return const CartSkeleton();
    }

    if (cartState.error != null &&
        (cartState.cart == null || cartState.cart!.isEmpty)) {
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
        icon: Icons.shopping_bag_outlined,
        actionLabel: l10n.browseFeed,
        onAction: () => context.go('/home'),
      );
    }

    return RefreshIndicator(
      onRefresh: () => ref.read(cartNotifierProvider.notifier).load(),
      child: ListView.separated(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(AppSpacing.lg),
        itemCount: cart.items.length,
        separatorBuilder: (_, __) => const SizedBox(height: AppSpacing.md),
        itemBuilder: (context, index) {
          final item = cart.items[index];
          return _CartLineCard(
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

class _CartLineCard extends StatelessWidget {
  const _CartLineCard({
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
    final palette = AppPalette.of(context);

    return Container(
      padding: const EdgeInsets.all(AppSpacing.md),
      decoration: BoxDecoration(
        color: palette.surfaceElevated,
        borderRadius: AppRadius.lgAll,
        border: Border.all(color: palette.outline),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          GestureDetector(
            onTap: () => context.push('/products/${item.product.id}'),
            child: item.product.thumbnail != null
                ? AppCachedImage(
                    url: item.product.thumbnail!,
                    width: 80,
                    height: 80,
                    memCacheWidth: 240,
                    borderRadius: AppRadius.smAll,
                  )
                : Container(
                    width: 80,
                    height: 80,
                    decoration: BoxDecoration(
                      color: palette.surfaceHigh,
                      borderRadius: AppRadius.smAll,
                    ),
                    child: Icon(
                      Icons.shopping_bag_outlined,
                      color: palette.textTertiary,
                    ),
                  ),
          ),
          const SizedBox(width: AppSpacing.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: Text(
                        item.product.title,
                        style: theme.textTheme.titleSmall,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                    IconButton(
                      onPressed: isMutating ? null : onRemove,
                      visualDensity: VisualDensity.compact,
                      tooltip: AppLocalizations.of(context)!.remove,
                      icon: Icon(
                        Icons.close_rounded,
                        size: 18,
                        color: palette.textTertiary,
                      ),
                    ),
                  ],
                ),
                if (item.variant != null)
                  Text(
                    '${item.variant!.name}: ${item.variant!.value}',
                    style: theme.textTheme.bodySmall
                        ?.copyWith(color: palette.textTertiary),
                  ),
                const SizedBox(height: AppSpacing.sm),
                Row(
                  children: [
                    _QuantityStepper(
                      quantity: item.quantity,
                      enabled: !isMutating,
                      onDecrease: onDecrease,
                      onIncrease: onIncrease,
                    ),
                    const Spacer(),
                    Text(
                      item.lineTotal.format(),
                      style: theme.textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _QuantityStepper extends StatelessWidget {
  const _QuantityStepper({
    required this.quantity,
    required this.enabled,
    required this.onDecrease,
    required this.onIncrease,
  });

  final int quantity;
  final bool enabled;
  final VoidCallback onDecrease;
  final VoidCallback onIncrease;

  @override
  Widget build(BuildContext context) {
    final palette = AppPalette.of(context);

    return Container(
      height: 34,
      decoration: BoxDecoration(
        color: palette.surfaceHigh,
        borderRadius: AppRadius.pillAll,
        border: Border.all(color: palette.outline),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          _StepperButton(
            icon: Icons.remove_rounded,
            onTap: enabled && quantity > 1 ? onDecrease : null,
          ),
          SizedBox(
            width: 28,
            child: Text(
              '$quantity',
              textAlign: TextAlign.center,
              style: Theme.of(context)
                  .textTheme
                  .titleSmall
                  ?.copyWith(fontWeight: FontWeight.w700),
            ),
          ),
          _StepperButton(
            icon: Icons.add_rounded,
            onTap: enabled ? onIncrease : null,
          ),
        ],
      ),
    );
  }
}

class _StepperButton extends StatelessWidget {
  const _StepperButton({required this.icon, this.onTap});

  final IconData icon;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final palette = AppPalette.of(context);

    return InkResponse(
      onTap: onTap,
      radius: 18,
      child: SizedBox(
        width: 34,
        height: 34,
        child: Icon(
          icon,
          size: 18,
          color: onTap == null ? palette.textTertiary : palette.textPrimary,
        ),
      ),
    );
  }
}

class _CartSummaryBar extends StatelessWidget {
  const _CartSummaryBar({required this.cart});

  final Cart cart;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final palette = AppPalette.of(context);
    final theme = Theme.of(context);

    return Container(
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
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    l10n.subtotal,
                    style: theme.textTheme.bodyMedium
                        ?.copyWith(color: palette.textSecondary),
                  ),
                  Text(
                    cart.summary.subtotal.format(),
                    style: theme.textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: AppSpacing.md),
              GradientButton(
                label: l10n.checkout,
                icon: Icons.lock_outline_rounded,
                onPressed: () => context.push('/checkout'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
