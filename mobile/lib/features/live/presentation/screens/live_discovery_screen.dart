import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:livecommerce_mobile/core/l10n/app_localizations.dart';
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
    final l10n = AppLocalizations.of(context)!;

    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.liveTitle),
        bottom: TabBar(
          controller: _tabs,
          tabs: [
            Tab(text: l10n.liveNowTab),
            Tab(text: l10n.replaysTab),
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
            emptyLabel: l10n.liveEmpty,
            refreshLabel: l10n.refresh,
            onRetry: () => ref.read(liveListProvider.notifier).load(),
            onRefresh: () => ref.read(liveListProvider.notifier).load(),
            onTap: (id) => context.push('/live/$id'),
            leading: CircleAvatar(
              backgroundColor: Colors.red,
              child: Text(
                l10n.liveBadge,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 10,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
            subtitleBuilder: (context, session) {
              final l = AppLocalizations.of(context)!;
              return l.watchingCount(
                session.seller?.username ?? 'seller',
                session.viewerCount,
              );
            },
          ),
          _SessionList(
            state: replays,
            emptyLabel: l10n.replaysEmpty,
            refreshLabel: l10n.refresh,
            onRetry: () => ref.read(liveReplayListProvider.notifier).load(),
            onRefresh: () => ref.read(liveReplayListProvider.notifier).load(),
            onTap: (id) => context.push('/live/replay/$id'),
            leading: const CircleAvatar(
              backgroundColor: Color(0xFF0F3460),
              child: Icon(Icons.replay, color: Colors.white, size: 18),
            ),
            subtitleBuilder: (context, session) {
              final l = AppLocalizations.of(context)!;
              final mins = ((session.durationSeconds ?? 0) / 60).ceil();
              return l.replayDuration(
                session.seller?.username ?? 'seller',
                mins,
              );
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
    required this.refreshLabel,
    required this.onRetry,
    required this.onRefresh,
    required this.onTap,
    required this.leading,
    required this.subtitleBuilder,
  });

  final LiveListState state;
  final String emptyLabel;
  final String refreshLabel;
  final VoidCallback onRetry;
  final Future<void> Function() onRefresh;
  final void Function(String id) onTap;
  final Widget leading;
  final String Function(BuildContext context, LiveSession session) subtitleBuilder;

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
                actionLabel: refreshLabel,
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
            subtitle: Text(subtitleBuilder(context, session)),
            trailing: const Icon(Icons.chevron_right),
            onTap: () => onTap(session.id),
          );
        },
      ),
    );
  }
}
