import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import 'package:intl/intl.dart';
import 'package:livecommerce_mobile/core/l10n/app_localizations.dart';
import 'package:livecommerce_mobile/core/theme/app_colors.dart';
import 'package:livecommerce_mobile/core/theme/app_dimens.dart';
import 'package:livecommerce_mobile/features/auth/presentation/providers/auth_providers.dart';
import 'package:livecommerce_mobile/features/messaging/data/media_upload_repository.dart';
import 'package:livecommerce_mobile/features/messaging/data/messaging_repository.dart';
import 'package:livecommerce_mobile/features/messaging/domain/entities/conversation.dart';
import 'package:livecommerce_mobile/features/messaging/presentation/providers/messaging_providers.dart';
import 'package:livecommerce_mobile/shared/widgets/app_cached_image.dart';
import 'package:livecommerce_mobile/shared/widgets/emoji_picker.dart';
import 'package:livecommerce_mobile/shared/widgets/error_widget.dart';
import 'package:livecommerce_mobile/shared/widgets/user_avatar.dart';

class ChatScreen extends ConsumerStatefulWidget {
  const ChatScreen({
    super.key,
    required this.conversationId,
    this.initialConversation,
  });

  final String conversationId;
  final Conversation? initialConversation;

  @override
  ConsumerState<ChatScreen> createState() => _ChatScreenState();
}

class _ChatScreenState extends ConsumerState<ChatScreen> {
  final _textController = TextEditingController();
  final _scrollController = ScrollController();
  final _focus = FocusNode();
  final _picker = ImagePicker();
  Timer? _pollTimer;
  bool _uploadingImage = false;
  bool _emojiOpen = false;

  @override
  void initState() {
    super.initState();
    _focus.addListener(() {
      if (_focus.hasFocus && _emojiOpen) {
        setState(() => _emojiOpen = false);
      }
    });
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(chatNotifierProvider(widget.conversationId).notifier).load();
      _startPolling();
    });
  }

  void _startPolling() {
    _pollTimer = Timer.periodic(const Duration(seconds: 5), (_) {
      if (mounted) {
        ref.read(chatNotifierProvider(widget.conversationId).notifier).poll();
      }
    });
  }

  @override
  void dispose() {
    _pollTimer?.cancel();
    _textController.dispose();
    _scrollController.dispose();
    _focus.dispose();
    super.dispose();
  }

  Future<void> _send() async {
    final text = _textController.text.trim();
    if (text.isEmpty) return;
    _textController.clear();
    final success = await ref
        .read(chatNotifierProvider(widget.conversationId).notifier)
        .sendMessage(text);
    if (!mounted) return;
    if (!success) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(AppLocalizations.of(context)!.sendMessageFailed),
        ),
      );
    } else {
      _scrollToBottom();
    }
  }

  void _insertEmoji(String emoji) {
    final selection = _textController.selection;
    final text = _textController.text;
    final start = selection.start < 0 ? text.length : selection.start;
    final end = selection.end < 0 ? text.length : selection.end;
    _textController.value = TextEditingValue(
      text: text.replaceRange(start, end, emoji),
      selection: TextSelection.collapsed(offset: start + emoji.length),
    );
    setState(() {});
  }

  void _backspace() {
    final text = _textController.text;
    if (text.isEmpty) return;
    final trimmed = text.characters.skipLast(1).toString();
    _textController.value = TextEditingValue(
      text: trimmed,
      selection: TextSelection.collapsed(offset: trimmed.length),
    );
    setState(() {});
  }

  void _toggleEmoji() {
    if (_emojiOpen) {
      setState(() => _emojiOpen = false);
      _focus.requestFocus();
      return;
    }
    FocusScope.of(context).unfocus();
    setState(() => _emojiOpen = true);
  }

  Future<void> _attachImage() async {
    final l10n = AppLocalizations.of(context)!;
    final chatState = ref.read(chatNotifierProvider(widget.conversationId));
    if (chatState.isSending || _uploadingImage) return;

    final picked = await _picker.pickImage(
      source: ImageSource.gallery,
      maxWidth: 1920,
      maxHeight: 1920,
      imageQuality: 85,
    );
    if (picked == null || !mounted) return;

    setState(() => _uploadingImage = true);
    try {
      final bytes = await picked.readAsBytes();
      final mimeType = _mimeFromName(picked.name);
      final publicUrl =
          await ref.read(mediaUploadRepositoryProvider).uploadMessageImage(
                fileName: picked.name,
                mimeType: mimeType,
                bytes: bytes,
              );
      final caption = _textController.text.trim();
      _textController.clear();
      final success = await ref
          .read(chatNotifierProvider(widget.conversationId).notifier)
          .sendMessage(caption, imageUrl: publicUrl);
      if (!mounted) return;
      if (!success) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(l10n.sendMessageFailed)),
        );
      } else {
        _scrollToBottom();
      }
    } catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(l10n.imageUploadFailed)),
      );
    } finally {
      if (mounted) setState(() => _uploadingImage = false);
    }
  }

  String _mimeFromName(String name) {
    final lower = name.toLowerCase();
    if (lower.endsWith('.png')) return 'image/png';
    if (lower.endsWith('.webp')) return 'image/webp';
    if (lower.endsWith('.gif')) return 'image/gif';
    return 'image/jpeg';
  }

  Future<void> _blockPeer() async {
    final peerId = widget.initialConversation?.participant.id;
    if (peerId == null) return;
    final l10n = AppLocalizations.of(context)!;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(l10n.blockUserTitle),
        content: Text(l10n.blockUserConfirm),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: Text(l10n.cancelButton),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: Text(l10n.blockUserAction),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;

    try {
      await ref.read(messagingRepositoryProvider).blockUser(peerId);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(l10n.userBlocked)),
      );
      Navigator.of(context).pop();
    } catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(l10n.blockUserFailed)),
      );
    }
  }

  Future<void> _unblockPeer() async {
    final peerId = widget.initialConversation?.participant.id;
    if (peerId == null) return;
    final l10n = AppLocalizations.of(context)!;
    try {
      await ref.read(messagingRepositoryProvider).unblockUser(peerId);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(l10n.userUnblocked)),
      );
    } catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(l10n.unblockUserFailed)),
      );
    }
  }

  void _scrollToBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scrollController.hasClients) {
        _scrollController.animateTo(
          _scrollController.position.maxScrollExtent,
          duration: AppDuration.normal,
          curve: AppDuration.curve,
        );
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final palette = AppPalette.of(context);
    final chatState = ref.watch(chatNotifierProvider(widget.conversationId));
    final auth = ref.watch(authNotifierProvider);
    final currentUserId = auth.user?.id;
    final busy = chatState.isSending || _uploadingImage;

    ref.listen(chatNotifierProvider(widget.conversationId), (prev, next) {
      if (prev != null && next.messages.length > prev.messages.length) {
        _scrollToBottom();
      }
    });

    final peer = widget.initialConversation?.participant;
    final hasPeer = peer?.id != null;

    return Scaffold(
      appBar: AppBar(
        titleSpacing: 0,
        title: Row(
          children: [
            UserAvatar(
              username: peer?.username ?? '?',
              avatarUrl: peer?.avatarUrl,
              radius: 18,
            ),
            const SizedBox(width: AppSpacing.md),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    peer?.displayName ?? l10n.messagesTitle,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.titleSmall?.copyWith(
                          fontWeight: FontWeight.w700,
                        ),
                  ),
                  if (peer != null)
                    Text(
                      '@${peer.username}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                            color: palette.textTertiary,
                          ),
                    ),
                ],
              ),
            ),
          ],
        ),
        actions: [
          if (hasPeer)
            PopupMenuButton<String>(
              icon: const Icon(Icons.more_vert_rounded),
              onSelected: (value) {
                if (value == 'block') {
                  _blockPeer();
                } else if (value == 'unblock') {
                  _unblockPeer();
                }
              },
              itemBuilder: (context) => [
                PopupMenuItem(value: 'block', child: Text(l10n.blockUserAction)),
                PopupMenuItem(
                  value: 'unblock',
                  child: Text(l10n.unblockUserAction),
                ),
              ],
            ),
        ],
      ),
      body: Column(
        children: [
          Expanded(child: _buildMessageList(chatState, currentUserId, l10n)),
          Divider(height: 1, color: palette.outline),
          _buildInputBar(chatState, l10n, busy),
          if (_emojiOpen)
            EmojiPickerPanel(onSelected: _insertEmoji, onBackspace: _backspace)
          else
            SizedBox(height: MediaQuery.paddingOf(context).bottom),
        ],
      ),
    );
  }

  Widget _buildMessageList(
    ChatState chatState,
    String? currentUserId,
    AppLocalizations l10n,
  ) {
    if (chatState.isLoading && chatState.messages.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }

    if (chatState.error != null && chatState.messages.isEmpty) {
      return ErrorDisplay(
        message: chatState.error!,
        onRetry: () =>
            ref.read(chatNotifierProvider(widget.conversationId).notifier).load(),
      );
    }

    if (chatState.messages.isEmpty) {
      return _ChatEmptyState(hint: l10n.typeMessageHint);
    }

    final messages = chatState.messages;

    return ListView.builder(
      controller: _scrollController,
      padding: const EdgeInsets.symmetric(
        horizontal: AppSpacing.md,
        vertical: AppSpacing.md,
      ),
      itemCount: messages.length,
      itemBuilder: (context, index) {
        final message = messages[index];
        final isMe = message.sender.id == currentUserId;
        final previous = index > 0 ? messages[index - 1] : null;
        final next = index + 1 < messages.length ? messages[index + 1] : null;

        final showDate = previous == null ||
            !_sameDay(previous.createdAt, message.createdAt);
        // Only the last bubble of a run gets the pointed corner and timestamp.
        final isRunEnd = next == null ||
            next.sender.id != message.sender.id ||
            !_sameDay(message.createdAt, next.createdAt);

        return Column(
          children: [
            if (showDate) _DateChip(iso: message.createdAt),
            _MessageBubble(
              message: message,
              isMe: isMe,
              isRunEnd: isRunEnd,
            ),
          ],
        );
      },
    );
  }

  static bool _sameDay(String a, String b) {
    final first = DateTime.tryParse(a)?.toLocal();
    final second = DateTime.tryParse(b)?.toLocal();
    if (first == null || second == null) {
      return true;
    }
    return first.year == second.year &&
        first.month == second.month &&
        first.day == second.day;
  }

  Widget _buildInputBar(ChatState chatState, AppLocalizations l10n, bool busy) {
    final palette = AppPalette.of(context);

    return Padding(
      padding: const EdgeInsets.fromLTRB(
        AppSpacing.sm,
        AppSpacing.sm,
        AppSpacing.md,
        AppSpacing.sm,
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          IconButton(
            onPressed: busy ? null : _attachImage,
            icon: _uploadingImage
                ? const SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(Icons.add_photo_alternate_outlined),
            tooltip: l10n.attachImage,
          ),
          Expanded(
            child: TextField(
              controller: _textController,
              focusNode: _focus,
              decoration: InputDecoration(
                hintText: l10n.typeMessageHint,
                isDense: true,
                contentPadding: const EdgeInsets.symmetric(
                  horizontal: AppSpacing.lg,
                  vertical: AppSpacing.md,
                ),
                border: const OutlineInputBorder(
                  borderRadius: AppRadius.xlAll,
                  borderSide: BorderSide.none,
                ),
                enabledBorder: OutlineInputBorder(
                  borderRadius: AppRadius.xlAll,
                  borderSide: BorderSide(color: palette.outline),
                ),
                focusedBorder: OutlineInputBorder(
                  borderRadius: AppRadius.xlAll,
                  borderSide: BorderSide(color: palette.brand, width: 1.6),
                ),
                suffixIcon: IconButton(
                  onPressed: _toggleEmoji,
                  icon: Icon(
                    _emojiOpen
                        ? Icons.keyboard_alt_outlined
                        : Icons.emoji_emotions_outlined,
                    color: _emojiOpen ? palette.brand : palette.textSecondary,
                  ),
                ),
              ),
              maxLines: 4,
              minLines: 1,
              textInputAction: TextInputAction.newline,
              onSubmitted: (_) => _send(),
            ),
          ),
          const SizedBox(width: AppSpacing.sm),
          _SendButton(
            busy: chatState.isSending && !_uploadingImage,
            onTap: busy ? null : _send,
          ),
        ],
      ),
    );
  }
}

class _SendButton extends StatelessWidget {
  const _SendButton({required this.busy, required this.onTap});

  final bool busy;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: 46,
        height: 46,
        decoration: BoxDecoration(
          gradient: AppGradients.brand,
          shape: BoxShape.circle,
          boxShadow: AppShadows.brandGlow(opacity: 0.25),
        ),
        child: busy
            ? const Padding(
                padding: EdgeInsets.all(13),
                child: CircularProgressIndicator(
                  strokeWidth: 2,
                  color: Colors.white,
                ),
              )
            : const Icon(Icons.send_rounded, color: Colors.white, size: 20),
      ),
    );
  }
}

class _ChatEmptyState extends StatelessWidget {
  const _ChatEmptyState({required this.hint});

  final String hint;

  @override
  Widget build(BuildContext context) {
    final palette = AppPalette.of(context);

    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 76,
            height: 76,
            decoration: BoxDecoration(
              gradient: AppGradients.brandDiagonal,
              shape: BoxShape.circle,
              boxShadow: AppShadows.brandGlow(opacity: 0.25),
            ),
            child: const Icon(
              Icons.waving_hand_rounded,
              color: Colors.white,
              size: 34,
            ),
          ),
          const SizedBox(height: AppSpacing.lg),
          Text(
            hint,
            style: Theme.of(context)
                .textTheme
                .bodyMedium
                ?.copyWith(color: palette.textTertiary),
          ),
        ],
      ),
    );
  }
}

class _DateChip extends StatelessWidget {
  const _DateChip({required this.iso});

  final String iso;

  @override
  Widget build(BuildContext context) {
    final palette = AppPalette.of(context);
    final parsed = DateTime.tryParse(iso)?.toLocal();
    if (parsed == null) {
      return const SizedBox.shrink();
    }

    final locale = Localizations.localeOf(context).toLanguageTag();
    final label = DateFormat.MMMd(locale).format(parsed);

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: AppSpacing.md),
      child: Container(
        padding: const EdgeInsets.symmetric(
          horizontal: AppSpacing.md,
          vertical: AppSpacing.xs + 1,
        ),
        decoration: BoxDecoration(
          color: palette.surfaceHigh,
          borderRadius: AppRadius.pillAll,
        ),
        child: Text(
          label,
          style: Theme.of(context)
              .textTheme
              .labelSmall
              ?.copyWith(color: palette.textSecondary),
        ),
      ),
    );
  }
}

class _MessageBubble extends StatelessWidget {
  const _MessageBubble({
    required this.message,
    required this.isMe,
    required this.isRunEnd,
  });

  final ChatMessage message;
  final bool isMe;
  final bool isRunEnd;

  /// Short emoji-only messages render as stickers, without a bubble.
  bool get _isEmojiOnly {
    final body = message.body?.trim() ?? '';
    if (body.isEmpty || message.imageUrl != null) {
      return false;
    }
    final graphemes = body.characters;
    if (graphemes.length > 3) {
      return false;
    }
    return !RegExp(r'[0-9A-Za-zА-Яа-яЎўҚқҒғҲҳ]').hasMatch(body);
  }

  @override
  Widget build(BuildContext context) {
    final palette = AppPalette.of(context);
    final theme = Theme.of(context);
    final time = _formatTime(context, message.createdAt);
    final maxWidth = MediaQuery.sizeOf(context).width * 0.74;

    if (_isEmojiOnly) {
      return Align(
        alignment: isMe ? Alignment.centerRight : Alignment.centerLeft,
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: AppSpacing.xs),
          child: Column(
            crossAxisAlignment:
                isMe ? CrossAxisAlignment.end : CrossAxisAlignment.start,
            children: [
              Text(message.body!.trim(), style: const TextStyle(fontSize: 46)),
              if (isRunEnd)
                Text(
                  time,
                  style: theme.textTheme.labelSmall
                      ?.copyWith(color: palette.textTertiary),
                ),
            ],
          ),
        ),
      );
    }

    final fgColor = isMe ? Colors.white : palette.textPrimary;
    final radius = BorderRadius.only(
      topLeft: const Radius.circular(AppRadius.lg),
      topRight: const Radius.circular(AppRadius.lg),
      bottomLeft: Radius.circular(isMe || !isRunEnd ? AppRadius.lg : AppRadius.xs),
      bottomRight:
          Radius.circular(!isMe || !isRunEnd ? AppRadius.lg : AppRadius.xs),
    );

    return Align(
      alignment: isMe ? Alignment.centerRight : Alignment.centerLeft,
      child: Container(
        margin: EdgeInsets.only(bottom: isRunEnd ? AppSpacing.md : AppSpacing.xs),
        padding: const EdgeInsets.fromLTRB(
          AppSpacing.lg - 2,
          AppSpacing.sm + 2,
          AppSpacing.md,
          AppSpacing.sm,
        ),
        constraints: BoxConstraints(maxWidth: maxWidth),
        decoration: BoxDecoration(
          gradient: isMe ? AppGradients.brand : null,
          color: isMe ? null : palette.surfaceElevated,
          borderRadius: radius,
          border: isMe ? null : Border.all(color: palette.outline),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            if (message.imageUrl != null)
              Padding(
                padding: message.body != null && message.body!.isNotEmpty
                    ? const EdgeInsets.only(bottom: AppSpacing.sm)
                    : EdgeInsets.zero,
                child: AppCachedImage(
                  url: message.imageUrl!,
                  height: 200,
                  width: maxWidth,
                  borderRadius: AppRadius.smAll,
                  errorWidget: Icon(
                    Icons.broken_image_outlined,
                    color: fgColor,
                  ),
                ),
              ),
            if (message.body != null && message.body!.isNotEmpty)
              Text(
                message.body!,
                style: theme.textTheme.bodyMedium
                    ?.copyWith(color: fgColor, height: 1.3),
              ),
            const SizedBox(height: AppSpacing.xxs),
            Align(
              alignment: Alignment.centerRight,
              child: Text(
                time,
                style: theme.textTheme.labelSmall?.copyWith(
                  fontSize: 10,
                  color: isMe
                      ? Colors.white.withValues(alpha: 0.75)
                      : palette.textTertiary,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  static String _formatTime(BuildContext context, String iso) {
    final parsed = DateTime.tryParse(iso)?.toLocal();
    if (parsed == null) {
      return '';
    }
    final locale = Localizations.localeOf(context).toLanguageTag();
    return DateFormat.Hm(locale).format(parsed);
  }
}
