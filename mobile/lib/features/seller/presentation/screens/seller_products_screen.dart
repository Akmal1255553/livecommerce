import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:livecommerce_mobile/features/seller/presentation/providers/seller_providers.dart';

class SellerProductsScreen extends ConsumerStatefulWidget {
  const SellerProductsScreen({super.key});

  @override
  ConsumerState<SellerProductsScreen> createState() =>
      _SellerProductsScreenState();
}

class _SellerProductsScreenState extends ConsumerState<SellerProductsScreen> {
  @override
  void initState() {
    super.initState();
    Future.microtask(() => ref.read(sellerProductsProvider.notifier).load());
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(sellerProductsProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('My products')),
      floatingActionButton: FloatingActionButton(
        onPressed: () => context.push('/seller/products/new'),
        child: const Icon(Icons.add),
      ),
      body: state.isLoading && state.products.isEmpty
          ? const Center(child: CircularProgressIndicator())
          : state.error != null && state.products.isEmpty
              ? Center(child: Text(state.error!))
              : state.products.isEmpty
                  ? const Center(child: Text('No products yet'))
                  : ListView.separated(
                      padding: const EdgeInsets.all(16),
                      itemCount: state.products.length,
                      separatorBuilder: (_, __) => const Divider(),
                      itemBuilder: (context, index) {
                        final product = state.products[index];
                        final price =
                            NumberFormat('#,###').format(product.price.toInt());
                        return ListTile(
                          leading: product.thumbnail != null
                              ? Image.network(
                                  product.thumbnail!,
                                  width: 48,
                                  height: 48,
                                  fit: BoxFit.cover,
                                )
                              : const Icon(Icons.shopping_bag_outlined),
                          title: Text(product.title),
                          subtitle: Text(
                            '$price UZS · stock ${product.stockQuantity} · ${product.status}',
                          ),
                          trailing: IconButton(
                            icon: const Icon(Icons.delete_outline),
                            onPressed: () async {
                              final ok = await ref
                                  .read(sellerProductsProvider.notifier)
                                  .remove(product.id);
                              if (!context.mounted) {
                                return;
                              }
                              if (!ok) {
                                final error = ref.read(sellerProductsProvider).error;
                                if (error != null) {
                                  ScaffoldMessenger.of(context).showSnackBar(
                                    SnackBar(content: Text(error)),
                                  );
                                }
                              }
                            },
                          ),
                        );
                      },
                    ),
    );
  }
}
