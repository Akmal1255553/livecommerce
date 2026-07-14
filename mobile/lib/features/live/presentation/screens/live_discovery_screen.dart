import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:livecommerce_mobile/features/live/presentation/providers/live_providers.dart';

class LiveDiscoveryScreen extends ConsumerStatefulWidget {
  const LiveDiscoveryScreen({super.key});

  @override
  ConsumerState<LiveDiscoveryScreen> createState() => _LiveDiscoveryScreenState();
}

class _LiveDiscoveryScreenState extends ConsumerState<LiveDiscoveryScreen> {
  @override
  void initState() {
    super.initState();
    Future.microtask(() => ref.read(liveListProvider.notifier).load());
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(liveListProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Live now'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: () => ref.read(liveListProvider.notifier).load(),
          ),
        ],
      ),
      body: state.isLoading && state.sessions.isEmpty
          ? const Center(child: CircularProgressIndicator())
          : state.error != null && state.sessions.isEmpty
              ? Center(child: Text(state.error!))
              : state.sessions.isEmpty
                  ? const Center(child: Text('No live sessions right now'))
                  : ListView.separated(
                      padding: const EdgeInsets.all(16),
                      itemCount: state.sessions.length,
                      separatorBuilder: (_, __) => const Divider(),
                      itemBuilder: (context, index) {
                        final session = state.sessions[index];
                        return ListTile(
                          leading: const CircleAvatar(
                            backgroundColor: Colors.red,
                            child: Text(
                              'LIVE',
                              style: TextStyle(
                                color: Colors.white,
                                fontSize: 10,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                          ),
                          title: Text(session.title),
                          subtitle: Text(
                            '@${session.seller?.username ?? 'seller'} · ${session.viewerCount} watching',
                          ),
                          trailing: const Icon(Icons.chevron_right),
                          onTap: () => context.push('/live/${session.id}'),
                        );
                      },
                    ),
    );
  }
}
