import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:livecommerce_mobile/features/seller/presentation/providers/seller_providers.dart';

class SellerLiveAnalyticsScreen extends ConsumerStatefulWidget {
  const SellerLiveAnalyticsScreen({super.key});

  @override
  ConsumerState<SellerLiveAnalyticsScreen> createState() =>
      _SellerLiveAnalyticsScreenState();
}

class _SellerLiveAnalyticsScreenState
    extends ConsumerState<SellerLiveAnalyticsScreen> {
  @override
  void initState() {
    super.initState();
    Future.microtask(
      () => ref.read(liveAnalyticsOverviewProvider.notifier).load(),
    );
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(liveAnalyticsOverviewProvider);
    final overview = state.overview;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Live analytics'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: () =>
                ref.read(liveAnalyticsOverviewProvider.notifier).load(),
          ),
        ],
      ),
      body: state.isLoading && overview == null
          ? const Center(child: CircularProgressIndicator())
          : state.error != null && overview == null
              ? Center(child: Text(state.error!))
              : overview == null
                  ? const Center(child: Text('No live sessions yet'))
                  : ListView(
                      padding: const EdgeInsets.all(16),
                      children: [
                        Wrap(
                          spacing: 12,
                          runSpacing: 12,
                          children: [
                            _StatCard(
                              label: 'Sessions',
                              value: '${overview.totalSessions}',
                            ),
                            _StatCard(
                              label: 'Unique viewers',
                              value: '${overview.totalUniqueViewers}',
                            ),
                            _StatCard(
                              label: 'Peak viewers',
                              value: '${overview.totalPeakViewers}',
                            ),
                            _StatCard(
                              label: 'Add to cart',
                              value: '${overview.totalAddToCart}',
                            ),
                            _StatCard(
                              label: 'Pins',
                              value: '${overview.totalPins}',
                            ),
                            _StatCard(
                              label: 'Avg cart rate',
                              value:
                                  '${(overview.avgCartConversionRate * 100).toStringAsFixed(1)}%',
                            ),
                          ],
                        ),
                        const SizedBox(height: 24),
                        Text(
                          'Sessions',
                          style: Theme.of(context).textTheme.titleMedium,
                        ),
                        const SizedBox(height: 8),
                        if (overview.sessions.isEmpty)
                          const Text('No sessions yet — Go Live to collect metrics')
                        else
                          ...overview.sessions.map(
                            (session) => Card(
                              child: ListTile(
                                title: Text(session.title),
                                subtitle: Text(
                                  '${session.status} · ${session.uniqueViewers} unique · '
                                  '${session.productsAddedToCart} cart',
                                ),
                                trailing: const Icon(Icons.chevron_right),
                                onTap: () => context.push(
                                  '/seller/live/${session.sessionId}/analytics',
                                ),
                              ),
                            ),
                          ),
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
