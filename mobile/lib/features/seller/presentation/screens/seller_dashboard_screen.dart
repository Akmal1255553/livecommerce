import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:livecommerce_mobile/features/seller/presentation/providers/seller_providers.dart';

class SellerDashboardScreen extends ConsumerStatefulWidget {
  const SellerDashboardScreen({super.key});

  @override
  ConsumerState<SellerDashboardScreen> createState() =>
      _SellerDashboardScreenState();
}

class _SellerDashboardScreenState extends ConsumerState<SellerDashboardScreen> {
  @override
  void initState() {
    super.initState();
    Future.microtask(() => ref.read(sellerDashboardProvider.notifier).load());
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(sellerDashboardProvider);
    final dash = state.dashboard;
    final revenue = NumberFormat('#,###').format(dash?.totalRevenue ?? 0);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Seller dashboard'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: () => ref.read(sellerDashboardProvider.notifier).load(),
          ),
        ],
      ),
      body: state.isLoading && dash == null
          ? const Center(child: CircularProgressIndicator())
          : state.error != null && dash == null
              ? Center(child: Text(state.error!))
              : ListView(
                  padding: const EdgeInsets.all(16),
                  children: [
                    if (dash != null) ...[
                      Text(
                        dash.store.name,
                        style: Theme.of(context).textTheme.headlineSmall,
                      ),
                      Text('@${dash.store.slug} · ${dash.store.status}'),
                      const SizedBox(height: 16),
                      Wrap(
                        spacing: 12,
                        runSpacing: 12,
                        children: [
                          _StatCard(label: 'Revenue', value: '$revenue ${dash.currency}'),
                          _StatCard(label: 'Orders', value: '${dash.totalOrders}'),
                          _StatCard(label: 'Pending', value: '${dash.pendingOrders}'),
                          _StatCard(label: 'Products', value: '${dash.totalProducts}'),
                          _StatCard(label: 'Active', value: '${dash.activeProducts}'),
                          _StatCard(label: 'Low stock', value: '${dash.lowStockProducts}'),
                        ],
                      ),
                      const SizedBox(height: 24),
                    ],
                    FilledButton.icon(
                      onPressed: () => context.push('/seller/products'),
                      icon: const Icon(Icons.inventory_2_outlined),
                      label: const Text('Manage products'),
                    ),
                    const SizedBox(height: 12),
                    OutlinedButton.icon(
                      onPressed: () => context.push('/seller/products/new'),
                      icon: const Icon(Icons.add),
                      label: const Text('Add product'),
                    ),
                    const SizedBox(height: 12),
                    OutlinedButton.icon(
                      onPressed: () => context.push('/seller/orders'),
                      icon: const Icon(Icons.receipt_long_outlined),
                      label: const Text('Seller orders'),
                    ),
                    if (dash != null) ...[
                      const SizedBox(height: 12),
                      TextButton(
                        onPressed: () => context.push('/stores/${dash.store.slug}'),
                        child: const Text('View public store'),
                      ),
                    ],
                  ],
                ),
    );
  }
}

class _StatCard extends StatelessWidget {
  const _StatCard({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 150,
      child: Card(
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(label, style: Theme.of(context).textTheme.labelMedium),
              const SizedBox(height: 4),
              Text(
                value,
                style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w700,
                    ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
