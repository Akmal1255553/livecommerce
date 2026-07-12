import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:livecommerce_mobile/features/seller/presentation/providers/seller_providers.dart';

class PublicStoreScreen extends ConsumerWidget {
  const PublicStoreScreen({super.key, required this.slug});

  final String slug;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(publicStoreProvider(slug));

    return Scaffold(
      appBar: AppBar(title: Text(slug)),
      body: async.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(child: Text('$e')),
        data: (page) => ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Text(
              page.store.name,
              style: Theme.of(context).textTheme.headlineSmall,
            ),
            if (page.store.description != null &&
                page.store.description!.isNotEmpty) ...[
              const SizedBox(height: 8),
              Text(page.store.description!),
            ],
            const SizedBox(height: 16),
            Text(
              'Products',
              style: Theme.of(context).textTheme.titleMedium,
            ),
            const SizedBox(height: 8),
            if (page.products.isEmpty)
              const Text('No products yet')
            else
              ...page.products.map((product) {
                final price =
                    NumberFormat('#,###').format(product.price.toInt());
                return ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: product.thumbnail != null
                      ? Image.network(
                          product.thumbnail!,
                          width: 48,
                          height: 48,
                          fit: BoxFit.cover,
                        )
                      : const Icon(Icons.shopping_bag_outlined),
                  title: Text(product.title),
                  subtitle: Text('$price UZS'),
                  onTap: () => context.push('/products/${product.id}'),
                );
              }),
          ],
        ),
      ),
    );
  }
}
