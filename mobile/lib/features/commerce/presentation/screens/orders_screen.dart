import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
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
      appBar: AppBar(title: const Text('My orders')),
      body: _buildBody(ordersState),
    );
  }

  Widget _buildBody(OrdersState state) {
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
      return EmptyState(
        title: 'No orders yet',
        subtitle: 'When you check out, your orders will appear here.',
        icon: Icons.receipt_long_outlined,
        actionLabel: 'Browse feed',
        onAction: () => context.go('/home'),
      );
    }

    return ListView.separated(
      controller: _scrollController,
      padding: const EdgeInsets.all(16),
      itemCount: state.orders.length + (state.isLoadingMore ? 1 : 0),
      separatorBuilder: (_, __) => const Divider(),
      itemBuilder: (context, index) {
        if (index >= state.orders.length) {
          return const Padding(
            padding: EdgeInsets.all(16),
            child: LoadingIndicator(),
          );
        }

        final order = state.orders[index];
        return _OrderListTile(order: order);
      },
    );
  }
}

class _OrderListTile extends StatelessWidget {
  const _OrderListTile({required this.order});

  final Order order;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return ListTile(
      contentPadding: EdgeInsets.zero,
      title: Text('#${order.orderNumber}'),
      subtitle: Text('${order.status} · ${order.totals.total.format()}'),
      trailing: const Icon(Icons.chevron_right),
      onTap: () => context.push('/orders/${order.id}'),
      titleTextStyle: theme.textTheme.titleMedium?.copyWith(
        fontWeight: FontWeight.w600,
      ),
    );
  }
}
