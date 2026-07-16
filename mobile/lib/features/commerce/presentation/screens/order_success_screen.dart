import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:livecommerce_mobile/features/commerce/domain/entities/order.dart';
import 'package:livecommerce_mobile/features/commerce/presentation/providers/commerce_providers.dart';

class OrderSuccessScreen extends ConsumerWidget {
  const OrderSuccessScreen({
    super.key,
    required this.orderId,
    this.initialOrder,
  });

  final String orderId;
  final Order? initialOrder;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    if (initialOrder != null) {
      return _OrderSuccessBody(order: initialOrder!);
    }

    final orderAsync = ref.watch(orderDetailProvider(orderId));

    return orderAsync.when(
      loading: () => const Scaffold(
        body: Center(child: CircularProgressIndicator()),
      ),
      error: (error, _) => Scaffold(
        appBar: AppBar(title: const Text('Order placed')),
        body: Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text(error.toString(), textAlign: TextAlign.center),
                const SizedBox(height: 16),
                FilledButton(
                  onPressed: () => context.go('/orders'),
                  child: const Text('View orders'),
                ),
              ],
            ),
          ),
        ),
      ),
      data: (order) => _OrderSuccessBody(order: order),
    );
  }
}

class _OrderSuccessBody extends StatelessWidget {
  const _OrderSuccessBody({required this.order});

  final Order order;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(title: const Text('Order placed')),
      body: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              Icons.check_circle_outline,
              size: 80,
              color: theme.colorScheme.primary,
            ),
            const SizedBox(height: 24),
            Text(
              'Thank you!',
              style: theme.textTheme.headlineMedium?.copyWith(
                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(height: 12),
            Text(
              'Order #${order.orderNumber}',
              style: theme.textTheme.titleLarge,
            ),
            const SizedBox(height: 8),
            Text(
              'Total: ${order.totals.total.format()}',
              style: theme.textTheme.titleMedium,
            ),
            const SizedBox(height: 8),
            Text(
              'Status: ${order.status}',
              style: theme.textTheme.bodyLarge,
            ),
            const SizedBox(height: 32),
            FilledButton(
              onPressed: () => context.go('/orders/${order.id}'),
              child: const Text('View order'),
            ),
            const SizedBox(height: 12),
            OutlinedButton(
              onPressed: () => context.go('/home'),
              child: const Text('Back to feed'),
            ),
          ],
        ),
      ),
    );
  }
}
