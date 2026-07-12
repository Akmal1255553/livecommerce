import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:livecommerce_mobile/features/seller/presentation/providers/seller_providers.dart';

const _statusFlow = [
  'packing',
  'ready_to_ship',
  'shipped',
  'delivered',
];

class SellerOrderDetailScreen extends ConsumerStatefulWidget {
  const SellerOrderDetailScreen({super.key, required this.orderId});

  final String orderId;

  @override
  ConsumerState<SellerOrderDetailScreen> createState() =>
      _SellerOrderDetailScreenState();
}

class _SellerOrderDetailScreenState
    extends ConsumerState<SellerOrderDetailScreen> {
  @override
  void initState() {
    super.initState();
    Future.microtask(
      () => ref.read(sellerOrderDetailProvider(widget.orderId).notifier).load(),
    );
  }

  Future<void> _updateStatus(String status) async {
    final ok = await ref
        .read(sellerOrderDetailProvider(widget.orderId).notifier)
        .updateStatus(status);
    if (!mounted) {
      return;
    }
    if (ok) {
      ref.read(sellerOrdersProvider.notifier).load();
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Status → $status')),
      );
    } else {
      final error = ref.read(sellerOrderDetailProvider(widget.orderId)).error;
      if (error != null) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error)));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(sellerOrderDetailProvider(widget.orderId));
    final order = state.order;

    return Scaffold(
      appBar: AppBar(title: Text(order?.orderNumber ?? 'Order')),
      body: state.isLoading && order == null
          ? const Center(child: CircularProgressIndicator())
          : state.error != null && order == null
              ? Center(child: Text(state.error!))
              : order == null
                  ? const Center(child: Text('Order not found'))
                  : ListView(
                      padding: const EdgeInsets.all(16),
                      children: [
                        Text(
                          order.status,
                          style: Theme.of(context).textTheme.titleLarge,
                        ),
                        const SizedBox(height: 8),
                        Text(order.totals.total.format()),
                        if (order.shippingAddress != null) ...[
                          const SizedBox(height: 8),
                          Text('Buyer: ${order.shippingAddress!.fullName}'),
                          Text(
                            '${order.shippingAddress!.city}, ${order.shippingAddress!.addressLine}',
                          ),
                        ],
                        if (order.items.isNotEmpty) ...[
                          const SizedBox(height: 16),
                          Text(
                            'Items',
                            style: Theme.of(context).textTheme.titleMedium,
                          ),
                          ...order.items.map(
                            (item) => ListTile(
                              contentPadding: EdgeInsets.zero,
                              title: Text(item.productTitle),
                              subtitle: Text('×${item.quantity}'),
                              trailing: Text(item.lineTotal.format()),
                            ),
                          ),
                        ],
                        const SizedBox(height: 24),
                        Text(
                          'Update status',
                          style: Theme.of(context).textTheme.titleMedium,
                        ),
                        const SizedBox(height: 8),
                        Wrap(
                          spacing: 8,
                          runSpacing: 8,
                          children: _statusFlow
                              .map(
                                (status) => OutlinedButton(
                                  onPressed: state.isUpdating
                                      ? null
                                      : () => _updateStatus(status),
                                  child: Text(status),
                                ),
                              )
                              .toList(),
                        ),
                      ],
                    ),
    );
  }
}
