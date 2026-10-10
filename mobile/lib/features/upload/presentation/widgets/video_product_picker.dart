import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:livecommerce_mobile/features/seller/presentation/providers/seller_providers.dart';

Future<Set<String>?> pickVideoProducts(BuildContext context, WidgetRef ref, Set<String> initial) async {
  await ref.read(sellerProductsProvider.notifier).load();
  if (!context.mounted) return null;
  final state = ref.read(sellerProductsProvider);
  final products = state.products.where((p) => p.status == 'active' && p.stockQuantity > 0).toList();
  if (state.error != null || products.isEmpty) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(
      state.error ?? 'Добавьте активный товар с остатком в панели продавца.',
    )));
    return null;
  }
  final selected = Set<String>.from(initial);
  return showModalBottomSheet<Set<String>>(
    context: context, isScrollControlled: true,
    builder: (context) => StatefulBuilder(builder: (context, update) =>
      SafeArea(child: SizedBox(height: MediaQuery.sizeOf(context).height * .65,
        child: Column(children: [
          const Padding(padding: EdgeInsets.all(16), child: Text('Товары на видео')),
          Expanded(child: ListView(children: products.map((product) =>
            CheckboxListTile(
              title: Text(product.title), subtitle: Text('${product.price.toInt()} UZS'),
              value: selected.contains(product.id),
              onChanged: (value) => update(() {
                if (value == true && selected.length < 20) selected.add(product.id);
                if (value == false) selected.remove(product.id);
              }),
            )).toList())),
          FilledButton(onPressed: () => Navigator.pop(context, selected), child: const Text('Сохранить товары')),
          const SizedBox(height: 16),
        ]),
      )),
    ),
  );
}
