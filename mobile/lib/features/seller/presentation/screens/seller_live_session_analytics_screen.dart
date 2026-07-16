import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:livecommerce_mobile/features/seller/presentation/providers/seller_providers.dart';

class SellerLiveSessionAnalyticsScreen extends ConsumerWidget {
  const SellerLiveSessionAnalyticsScreen({
    super.key,
    required this.sessionId,
  });

  final String sessionId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(liveSessionAnalyticsProvider(sessionId));

    return Scaffold(
      appBar: AppBar(title: const Text('Session analytics')),
      body: async.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => Center(child: Text('$error')),
        data: (session) {
          final mins = ((session.durationSeconds ?? 0) / 60).ceil();
          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Text(
                session.title,
                style: Theme.of(context).textTheme.headlineSmall,
              ),
              Text('${session.status} · ${mins}m'),
              const SizedBox(height: 16),
              Wrap(
                spacing: 12,
                runSpacing: 12,
                children: [
                  _Stat(label: 'Peak', value: '${session.peakViewers}'),
                  _Stat(label: 'Unique', value: '${session.uniqueViewers}'),
                  _Stat(label: 'Chat', value: '${session.chatMessages}'),
                  _Stat(label: 'Pins', value: '${session.productsPinned}'),
                  _Stat(
                    label: 'Add to cart',
                    value: '${session.productsAddedToCart}',
                  ),
                  _Stat(
                    label: 'Cart rate',
                    value:
                        '${(session.cartConversionRate * 100).toStringAsFixed(1)}%',
                  ),
                ],
              ),
              const SizedBox(height: 24),
              Text(
                'Top products',
                style: Theme.of(context).textTheme.titleMedium,
              ),
              const SizedBox(height: 8),
              if (session.topProducts.isEmpty)
                const Text('No product events yet')
              else
                ...session.topProducts.map(
                  (p) => ListTile(
                    contentPadding: EdgeInsets.zero,
                    title: Text(p.title ?? p.productId),
                    subtitle: Text('Pins ${p.pins} · Cart ${p.addToCart}'),
                    onTap: () => context.push('/products/${p.productId}'),
                  ),
                ),
              if (session.replayUrl != null) ...[
                const SizedBox(height: 16),
                OutlinedButton.icon(
                  onPressed: () =>
                      context.push('/live/replay/${session.sessionId}'),
                  icon: const Icon(Icons.replay),
                  label: const Text('Open replay'),
                ),
              ],
            ],
          );
        },
      ),
    );
  }
}

class _Stat extends StatelessWidget {
  const _Stat({required this.label, required this.value});

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
                style: Theme.of(context)
                    .textTheme
                    .titleMedium
                    ?.copyWith(fontWeight: FontWeight.w700),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
