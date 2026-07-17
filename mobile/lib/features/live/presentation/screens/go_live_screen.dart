import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:livecommerce_mobile/core/errors/error_handler.dart';
import 'package:livecommerce_mobile/core/l10n/app_localizations.dart';
import 'package:livecommerce_mobile/features/live/presentation/providers/live_providers.dart';
import 'package:livecommerce_mobile/features/seller/presentation/providers/seller_providers.dart';

class GoLiveScreen extends ConsumerStatefulWidget {
  const GoLiveScreen({super.key});

  @override
  ConsumerState<GoLiveScreen> createState() => _GoLiveScreenState();
}

class _GoLiveScreenState extends ConsumerState<GoLiveScreen> {
  final _titleController = TextEditingController();
  final Set<String> _selectedProductIds = {};
  bool _submitting = false;

  @override
  void initState() {
    super.initState();
    Future.microtask(() => ref.read(sellerProductsProvider.notifier).load());
  }

  @override
  void dispose() {
    _titleController.dispose();
    super.dispose();
  }

  Future<void> _start() async {
    final l10n = AppLocalizations.of(context)!;
    final rawTitle = _titleController.text.trim();
    final title = rawTitle.isEmpty ? l10n.defaultLiveTitle : rawTitle;
    if (title.length < 2) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(l10n.enterTitle)),
      );
      return;
    }

    setState(() => _submitting = true);
    try {
      final session = await ref.read(liveRepositoryProvider).start(
            title: title,
            productIds: _selectedProductIds.toList(),
          );
      if (!mounted) {
        return;
      }
      context.pushReplacement('/live/${session.id}?host=1');
    } catch (error) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(describeFailure(error))),
      );
    } finally {
      if (mounted) {
        setState(() => _submitting = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final productsState = ref.watch(sellerProductsProvider);
    final l10n = AppLocalizations.of(context)!;

    return Scaffold(
      appBar: AppBar(title: Text(l10n.goLiveTitle)),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text(
            l10n.goLiveChromeHint,
            style: Theme.of(context).textTheme.bodyMedium,
          ),
          const SizedBox(height: 16),
          TextField(
            controller: _titleController,
            decoration: InputDecoration(
              labelText: l10n.streamTitleLabel,
              hintText: l10n.streamTitleLabel,
            ),
          ),
          const SizedBox(height: 24),
          Text(l10n.attachProducts, style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 8),
          if (productsState.isLoading && productsState.products.isEmpty)
            const LinearProgressIndicator()
          else if (productsState.products.isEmpty)
            Text(l10n.noProductsYet)
          else
            ...productsState.products.map((product) {
              final selected = _selectedProductIds.contains(product.id);
              return CheckboxListTile(
                value: selected,
                contentPadding: EdgeInsets.zero,
                title: Text(product.title),
                subtitle: Text('${product.price.toInt()} UZS'),
                onChanged: (value) {
                  setState(() {
                    if (value == true) {
                      _selectedProductIds.add(product.id);
                    } else {
                      _selectedProductIds.remove(product.id);
                    }
                  });
                },
              );
            }),
          const SizedBox(height: 24),
          FilledButton.icon(
            onPressed: _submitting ? null : _start,
            icon: const Icon(Icons.podcasts),
            label: _submitting
                ? const SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : Text(l10n.startLive),
          ),
        ],
      ),
    );
  }
}
