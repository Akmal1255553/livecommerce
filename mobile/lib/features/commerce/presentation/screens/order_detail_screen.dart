import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:livecommerce_mobile/features/commerce/domain/entities/money_amount.dart';
import 'package:livecommerce_mobile/features/commerce/domain/entities/order.dart';
import 'package:livecommerce_mobile/features/commerce/presentation/providers/commerce_providers.dart';

class OrderDetailScreen extends ConsumerWidget {
  const OrderDetailScreen({super.key, required this.orderId});

  final String orderId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final orderAsync = ref.watch(orderDetailProvider(orderId));

    return Scaffold(
      appBar: AppBar(title: const Text('Order details')),
      body: orderAsync.when(
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
                  onPressed: () => ref.invalidate(orderDetailProvider(orderId)),
                  child: const Text('Retry'),
                ),
              ],
            ),
          ),
        ),
        data: (order) => _OrderDetailBody(order: order),
      ),
    );
  }
}

class _OrderDetailBody extends StatelessWidget {
  const _OrderDetailBody({required this.order});

  final Order order;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Text(
          '#${order.orderNumber}',
          style: theme.textTheme.headlineSmall?.copyWith(
            fontWeight: FontWeight.w700,
          ),
        ),
        const SizedBox(height: 4),
        Text('Status: ${order.status}'),
        if (order.paymentStatus != null)
          Text('Payment: ${order.paymentStatus}'),
        if (order.paymentMethod != null)
          Text('Method: ${order.paymentMethod}'),
        if (order.createdAt != null) Text('Placed: ${order.createdAt}'),
        if (order.isAwaitingPayment) ...[
          const SizedBox(height: 16),
          FilledButton(
            onPressed: () => context.push('/payment/${order.id}', extra: {
              'order': order,
            }),
            child: const Text('Continue payment'),
          ),
        ],
        if (order.canRequestRefund) ...[
          const SizedBox(height: 12),
          OutlinedButton(
            onPressed: () => context.push('/orders/${order.id}/refund'),
            child: const Text('Request refund'),
          ),
        ],
        const SizedBox(height: 20),
        Text('Items', style: theme.textTheme.titleMedium),
        const SizedBox(height: 8),
        ...order.items.map(
          (item) => ListTile(
            contentPadding: EdgeInsets.zero,
            title: Text(item.productTitle),
            subtitle: Text(
              '${item.quantity} × ${item.unitPrice.format()}'
              '${item.variantName != null ? ' · ${item.variantName}' : ''}',
            ),
            trailing: Text(item.lineTotal.format()),
          ),
        ),
        const Divider(height: 32),
        _TotalRow(label: 'Subtotal', amount: order.totals.subtotal),
        _TotalRow(label: 'Shipping', amount: order.totals.shipping),
        _TotalRow(label: 'Discount', amount: order.totals.discount),
        _TotalRow(label: 'Tax', amount: order.totals.tax),
        const SizedBox(height: 8),
        _TotalRow(
          label: 'Total',
          amount: order.totals.total,
          emphasized: true,
        ),
        if (order.shippingAddress != null) ...[
          const Divider(height: 32),
          Text('Shipping address', style: theme.textTheme.titleMedium),
          const SizedBox(height: 8),
          Text(order.shippingAddress!.fullName),
          Text(order.shippingAddress!.phone),
          Text(
            '${order.shippingAddress!.addressLine}, '
            '${order.shippingAddress!.city}, '
            '${order.shippingAddress!.region} '
            '${order.shippingAddress!.postalCode}',
          ),
        ],
      ],
    );
  }
}

class _TotalRow extends StatelessWidget {
  const _TotalRow({
    required this.label,
    required this.amount,
    this.emphasized = false,
  });

  final String label;
  final MoneyAmount amount;
  final bool emphasized;

  @override
  Widget build(BuildContext context) {
    final style = emphasized
        ? Theme.of(context).textTheme.titleMedium?.copyWith(
              fontWeight: FontWeight.w700,
            )
        : Theme.of(context).textTheme.bodyMedium;

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 2),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: style),
          Text(amount.format(), style: style),
        ],
      ),
    );
  }
}
