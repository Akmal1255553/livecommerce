import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:livecommerce_mobile/core/l10n/app_localizations.dart';
import 'package:livecommerce_mobile/core/theme/app_colors.dart';
import 'package:livecommerce_mobile/core/theme/app_dimens.dart';
import 'package:livecommerce_mobile/core/utils/time_format.dart';
import 'package:livecommerce_mobile/features/commerce/domain/entities/order.dart';
import 'package:livecommerce_mobile/features/commerce/presentation/providers/commerce_providers.dart';
import 'package:livecommerce_mobile/shared/widgets/empty_state.dart';
import 'package:livecommerce_mobile/shared/widgets/error_widget.dart';
import 'package:livecommerce_mobile/shared/widgets/loading_indicator.dart';
import 'package:livecommerce_mobile/shared/widgets/skeleton.dart';

class OrdersScreen extends ConsumerStatefulWidget {
  const OrdersScreen({super.key});

  @override
  ConsumerState<OrdersScreen> createState() => _OrdersScreenState();
}

class _OrdersScreenState extends ConsumerState<OrdersScreen> {
  final _scrollController = ScrollController();

  @override
  void initState() {
    super.initState();
    Future.microtask(() => ref.read(ordersNotifierProvider.notifier).load());
    _scrollController.addListener(_onScroll);
  }

  @override
  void dispose() {
    _scrollController.dispose();
    super.dispose();
  }

  void _onScroll() {
    if (_scrollController.position.pixels >=
        _scrollController.position.maxScrollExtent - 200) {
      ref.read(ordersNotifierProvider.notifier).loadMore();
    }
  }

  @override
  Widget build(BuildContext context) {
    final ordersState = ref.watch(ordersNotifierProvider);

    return Scaffold(
      appBar: AppBar(title: Text(AppLocalizations.of(context)!.ordersTitle)),
      body: _buildBody(context, ordersState),
    );
  }

  Widget _buildBody(BuildContext context, OrdersState state) {
    if (state.isLoading && state.orders.isEmpty) {
      return const ListSkeleton();
    }

    if (state.error != null && state.orders.isEmpty) {
      return ErrorDisplay(
        message: state.error!,
        onRetry: () => ref.read(ordersNotifierProvider.notifier).load(),
      );
    }

    if (state.orders.isEmpty) {
      final l10n = AppLocalizations.of(context)!;
      return EmptyState(
        title: l10n.ordersEmptyTitle,
        subtitle: l10n.ordersEmptySubtitle,
        icon: Icons.receipt_long_outlined,
        actionLabel: l10n.browseFeed,
        onAction: () => context.go('/home'),
      );
    }

    return RefreshIndicator(
      onRefresh: () => ref.read(ordersNotifierProvider.notifier).load(),
      child: ListView.separated(
        controller: _scrollController,
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(AppSpacing.lg),
        itemCount: state.orders.length + (state.isLoadingMore ? 1 : 0),
        separatorBuilder: (_, __) => const SizedBox(height: AppSpacing.md),
        itemBuilder: (context, index) {
          if (index >= state.orders.length) {
            return const Padding(
              padding: EdgeInsets.all(AppSpacing.lg),
              child: LoadingIndicator(),
            );
          }

          return _OrderCard(order: state.orders[index]);
        },
      ),
    );
  }
}

class _OrderCard extends StatelessWidget {
  const _OrderCard({required this.order});

  final Order order;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final palette = AppPalette.of(context);
    final theme = Theme.of(context);
    final time = formatTimeAgo(l10n, order.createdAt);
    final statusColor = _statusColor(palette);

    return Material(
      color: palette.surfaceElevated,
      borderRadius: AppRadius.lgAll,
      child: InkWell(
        onTap: () => context.push('/orders/${order.id}'),
        borderRadius: AppRadius.lgAll,
        child: Container(
          padding: const EdgeInsets.all(AppSpacing.lg),
          decoration: BoxDecoration(
            borderRadius: AppRadius.lgAll,
            border: Border.all(color: palette.outline),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      '#${order.orderNumber}',
                      style: theme.textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: AppSpacing.sm,
                      vertical: AppSpacing.xs,
                    ),
                    decoration: BoxDecoration(
                      color: statusColor.withValues(alpha: 0.12),
                      borderRadius: AppRadius.pillAll,
                    ),
                    child: Text(
                      order.status.replaceAll('_', ' '),
                      style: theme.textTheme.labelSmall?.copyWith(
                        color: statusColor,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: AppSpacing.sm),
              Row(
                children: [
                  Text(
                    order.totals.total.format(),
                    style: theme.textTheme.titleSmall?.copyWith(
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  if (time.isNotEmpty) ...[
                    Text(
                      '  ·  ',
                      style: TextStyle(color: palette.textTertiary),
                    ),
                    Text(
                      time,
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: palette.textTertiary,
                      ),
                    ),
                  ],
                  const Spacer(),
                  Icon(
                    Icons.chevron_right_rounded,
                    color: palette.textTertiary,
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  Color _statusColor(AppPalette palette) {
    return switch (order.status) {
      'paid' || 'delivered' || 'completed' => palette.success,
      'awaiting_payment' || 'pending' => palette.warning,
      'cancelled' || 'refunded' || 'refund_rejected' => palette.danger,
      'refund_requested' || 'refund_approved' => palette.info,
      _ => palette.textSecondary,
    };
  }
}
