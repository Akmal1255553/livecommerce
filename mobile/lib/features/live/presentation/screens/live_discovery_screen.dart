import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:livecommerce_mobile/features/live/domain/entities/live_session.dart';
import 'package:livecommerce_mobile/features/live/presentation/providers/live_providers.dart';
import 'package:livecommerce_mobile/shared/widgets/empty_state.dart';
import 'package:livecommerce_mobile/shared/widgets/error_widget.dart';
import 'package:livecommerce_mobile/shared/widgets/skeleton.dart';

class LiveDiscoveryScreen extends ConsumerStatefulWidget {
  const LiveDiscoveryScreen({super.key});

  @override
  ConsumerState<LiveDiscoveryScreen> createState() => _LiveDiscoveryScreenState();
}

class _LiveDiscoveryScreenState extends ConsumerState<LiveDiscoveryScreen>
    with SingleTickerProviderStateMixin {
  late final TabController _tabs;

  @override
  void initState() {
    super.initState();
    _tabs = TabController(length: 2, vsync: this);
    Future.microtask(() {
      ref.read(liveListProvider.notifier).load();
      ref.read(liveReplayListProvider.notifier).load();
    });
  }

  @override
  void dispose() {
    _tabs.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final live = ref.watch(liveListProvider);
    final replays = ref.watch(liveReplayListProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Live'),
        bottom: TabBar(
          controller: _tabs,
          tabs: const [
            Tab(text: 'Live now'),
            Tab(text: 'Replays'),
          ],
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: () {
              ref.read(liveListProvider.notifier).load();
              ref.read(liveReplayListProvider.notifier).load();
            },
          ),
        ],
      ),
      body: TabBarView(
        controller: _tabs,
        children: [
          _SessionList(
            state: live,
            emptyLabel: 'No live sessions right now',
            onRetry: () => ref.read(liveListProvider.notifier).load(),
            onRefresh: () => ref.read(liveListProvider.notifier).load(),
            onTap: (id) => context.push('/live/$id'),
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
            subtitleBuilder: (session) =>
                '@${session.seller?.username ?? 'seller'} · ${session.viewerCount} watching',
          ),
          _SessionList(
            state: replays,
            emptyLabel: 'No replays yet — end a live to generate one',
            onRetry: () => ref.read(liveReplayListProvider.notifier).load(),
            onRefresh: () => ref.read(liveReplayListProvider.notifier).load(),
            onTap: (id) => context.push('/live/replay/$id'),
            leading: const CircleAvatar(
              backgroundColor: Color(0xFF0F3460),
              child: Icon(Icons.replay, color: Colors.white, size: 18),
            ),
            subtitleBuilder: (session) {
              final mins = ((session.durationSeconds ?? 0) / 60).ceil();
              return '@${session.seller?.username ?? 'seller'} · ${mins}m replay';
            },
          ),
        ],
      ),
    );
  }
}

class _SessionList extends StatelessWidget {
  const _SessionList({
    required this.state,
    required this.emptyLabel,
    required this.onRetry,
    required this.onRefresh,
    required this.onTap,
    required this.leading,
    required this.subtitleBuilder,
  });

  final LiveListState state;
  final String emptyLabel;
  final VoidCallback onRetry;
  final Future<void> Function() onRefresh;
  final void Function(String id) onTap;
  final Widget leading;
  final String Function(LiveSession session) subtitleBuilder;

  @override
  Widget build(BuildContext context) {
    if (state.isLoading && state.sessions.isEmpty) {
      return const ListSkeleton(itemCount: 5);
    }

    if (state.error != null && state.sessions.isEmpty) {
      return ErrorDisplay(message: state.error!, onRetry: onRetry);
    }

    if (state.sessions.isEmpty) {
      return RefreshIndicator(
        onRefresh: onRefresh,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          children: [
            SizedBox(
              height: MediaQuery.sizeOf(context).height * 0.5,
              child: EmptyState(
                title: emptyLabel,
                icon: Icons.live_tv_outlined,
                actionLabel: 'Refresh',
                onAction: onRetry,
              ),
            ),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView.separated(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(16),
        itemCount: state.sessions.length,
        separatorBuilder: (_, __) => const Divider(),
        itemBuilder: (context, index) {
          final session = state.sessions[index];
          return ListTile(
            leading: leading,
            title: Text(session.title),
            subtitle: Text(subtitleBuilder(session)),
            trailing: const Icon(Icons.chevron_right),
            onTap: () => onTap(session.id),
          );
        },
      ),
    );
  }
}
