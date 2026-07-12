import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:livecommerce_mobile/features/seller/presentation/providers/seller_providers.dart';

class SellerOrdersScreen extends ConsumerStatefulWidget {
  const SellerOrdersScreen({super.key});

  @override
  ConsumerState<SellerOrdersScreen> createState() => _SellerOrdersScreenState();
}

class _SellerOrdersScreenState extends ConsumerState<SellerOrdersScreen> {
  @override
  void initState() {
    super.initState();
    Future.microtask(() => ref.read(sellerOrdersProvider.notifier).load());
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(sellerOrdersProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Seller orders'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: () => ref.read(sellerOrdersProvider.notifier).load(),
          ),
        ],
      ),
      body: state.isLoading && state.orders.isEmpty
          ? const Center(child: CircularProgressIndicator())
          : state.error != null && state.orders.isEmpty
              ? Center(child: Text(state.error!))
              : state.orders.isEmpty
                  ? const Center(child: Text('No orders yet'))
                  : ListView.separated(
                      padding: const EdgeInsets.all(16),
                      itemCount: state.orders.length,
                      separatorBuilder: (_, __) => const Divider(),
                      itemBuilder: (context, index) {
                        final order = state.orders[index];
                        final total =
                            NumberFormat('#,###').format(order.totalAmount.toInt());
                        return ListTile(
                          title: Text(order.orderNumber),
                          subtitle: Text(
                            '${order.status} · $total ${order.currency}',
                          ),
                          trailing: const Icon(Icons.chevron_right),
                          onTap: () => context.push('/seller/orders/${order.id}'),
                        );
                      },
                    ),
    );
  }
}
