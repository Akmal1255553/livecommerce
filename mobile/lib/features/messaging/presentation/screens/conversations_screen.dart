import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:livecommerce_mobile/core/l10n/app_localizations.dart';
import 'package:livecommerce_mobile/features/messaging/domain/entities/conversation.dart';
import 'package:livecommerce_mobile/features/messaging/presentation/providers/messaging_providers.dart';
import 'package:livecommerce_mobile/shared/widgets/empty_state.dart';
import 'package:livecommerce_mobile/shared/widgets/error_widget.dart';

class ConversationsScreen extends ConsumerStatefulWidget {
  const ConversationsScreen({super.key});

  @override
  ConsumerState<ConversationsScreen> createState() =>
      _ConversationsScreenState();
}

class _ConversationsScreenState extends ConsumerState<ConversationsScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(conversationsNotifierProvider.notifier).load();
    });
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final state = ref.watch(conversationsNotifierProvider);

    return Scaffold(
      appBar: AppBar(title: Text(l10n.messagesTitle)),
      body: RefreshIndicator(
        onRefresh: () =>
            ref.read(conversationsNotifierProvider.notifier).refresh(),
        child: _buildBody(state, l10n),
      ),
    );
  }

  Widget _buildBody(ConversationsState state, AppLocalizations l10n) {
    if (state.isLoading && state.conversations.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }

    if (state.error != null && state.conversations.isEmpty) {
      return ErrorDisplay(
        message: state.error!,
        onRetry: () =>
            ref.read(conversationsNotifierProvider.notifier).load(),
      );
    }

    if (state.conversations.isEmpty) {
      return ListView(
        children: [
          EmptyState(
            icon: Icons.chat_bubble_outline,
            title: l10n.noConversationsTitle,
            subtitle: l10n.noConversationsSubtitle,
          ),
        ],
      );
    }

    return ListView.separated(
      itemCount: state.conversations.length,
      separatorBuilder: (_, __) => const Divider(height: 1),
      itemBuilder: (context, index) {
        return _ConversationTile(conversation: state.conversations[index]);
      },
    );
  }
}

class _ConversationTile extends StatelessWidget {
  const _ConversationTile({required this.conversation});

  final Conversation conversation;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final p = conversation.participant;
    final hasUnread = conversation.unreadCount > 0;
    final initial = p.displayName.isNotEmpty
        ? p.displayName[0].toUpperCase()
        : '?';

    return ListTile(
      onTap: () => context.push(
        '/conversations/${conversation.id}',
        extra: conversation,
      ),
      leading: CircleAvatar(
        backgroundImage:
            p.avatarUrl != null ? NetworkImage(p.avatarUrl!) : null,
        child: p.avatarUrl == null ? Text(initial) : null,
      ),
      title: Text(
        p.displayName,
        style: theme.textTheme.bodyLarge?.copyWith(
          fontWeight: hasUnread ? FontWeight.w700 : FontWeight.w500,
        ),
      ),
      subtitle: conversation.lastMessagePreview != null
          ? Text(
              conversation.lastMessagePreview!,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: theme.textTheme.bodyMedium?.copyWith(
                color: hasUnread
                    ? theme.colorScheme.primary
                    : theme.colorScheme.outline,
                fontWeight:
                    hasUnread ? FontWeight.w600 : FontWeight.normal,
              ),
            )
          : null,
      trailing: hasUnread
          ? Container(
              padding:
                  const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
              decoration: BoxDecoration(
                color: theme.colorScheme.primary,
                borderRadius: BorderRadius.circular(12),
              ),
              child: Text(
                '${conversation.unreadCount}',
                style: theme.textTheme.labelSmall?.copyWith(
                  color: theme.colorScheme.onPrimary,
                ),
              ),
            )
          : null,
    );
  }
}
