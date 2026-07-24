import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:livecommerce_mobile/core/l10n/app_localizations.dart';
import 'package:livecommerce_mobile/core/theme/app_colors.dart';
import 'package:livecommerce_mobile/core/theme/app_dimens.dart';
import 'package:livecommerce_mobile/core/utils/time_format.dart';
import 'package:livecommerce_mobile/features/messaging/domain/entities/conversation.dart';
import 'package:livecommerce_mobile/features/messaging/presentation/providers/messaging_providers.dart';
import 'package:livecommerce_mobile/shared/widgets/empty_state.dart';
import 'package:livecommerce_mobile/shared/widgets/error_widget.dart';
import 'package:livecommerce_mobile/shared/widgets/skeleton.dart';
import 'package:livecommerce_mobile/shared/widgets/user_avatar.dart';

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
      return const ListSkeleton(itemCount: 7);
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
      padding: const EdgeInsets.symmetric(vertical: AppSpacing.sm),
      itemCount: state.conversations.length,
      separatorBuilder: (_, __) => const SizedBox(height: AppSpacing.xxs),
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
    final palette = AppPalette.of(context);
    final l10n = AppLocalizations.of(context)!;
    final p = conversation.participant;
    final hasUnread = conversation.unreadCount > 0;
    final time = formatTimeAgo(l10n, conversation.lastMessageAt);

    return InkWell(
      onTap: () => context.push(
        '/conversations/${conversation.id}',
        extra: conversation,
      ),
      child: Padding(
        padding: const EdgeInsets.symmetric(
          horizontal: AppSpacing.lg,
          vertical: AppSpacing.md,
        ),
        child: Row(
          children: [
            UserAvatar(
              username: p.username,
              avatarUrl: p.avatarUrl,
              radius: 26,
              ring: hasUnread,
            ),
            const SizedBox(width: AppSpacing.md),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          p.displayName,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: theme.textTheme.titleSmall?.copyWith(
                            fontWeight:
                                hasUnread ? FontWeight.w700 : FontWeight.w600,
                          ),
                        ),
                      ),
                      if (time.isNotEmpty)
                        Text(
                          time,
                          style: theme.textTheme.labelSmall?.copyWith(
                            color: hasUnread
                                ? palette.brand
                                : palette.textTertiary,
                          ),
                        ),
                    ],
                  ),
                  const SizedBox(height: AppSpacing.xxs),
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          conversation.lastMessagePreview ?? '',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: theme.textTheme.bodyMedium?.copyWith(
                            color: hasUnread
                                ? palette.textPrimary
                                : palette.textTertiary,
                            fontWeight:
                                hasUnread ? FontWeight.w600 : FontWeight.normal,
                          ),
                        ),
                      ),
                      if (hasUnread) ...[
                        const SizedBox(width: AppSpacing.sm),
                        Container(
                          constraints: const BoxConstraints(minWidth: 20),
                          padding: const EdgeInsets.symmetric(
                            horizontal: AppSpacing.xs + 2,
                            vertical: 1,
                          ),
                          decoration: const BoxDecoration(
                            gradient: AppGradients.brand,
                            borderRadius: AppRadius.pillAll,
                          ),
                          child: Text(
                            conversation.unreadCount > 99
                                ? '99+'
                                : '${conversation.unreadCount}',
                            textAlign: TextAlign.center,
                            style: theme.textTheme.labelSmall?.copyWith(
                              color: Colors.white,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ),
                      ],
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
