import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import 'package:livecommerce_mobile/core/l10n/app_localizations.dart';
import 'package:livecommerce_mobile/features/auth/presentation/providers/auth_providers.dart';
import 'package:livecommerce_mobile/features/messaging/data/media_upload_repository.dart';
import 'package:livecommerce_mobile/features/messaging/data/messaging_repository.dart';
import 'package:livecommerce_mobile/features/messaging/domain/entities/conversation.dart';
import 'package:livecommerce_mobile/features/messaging/presentation/providers/messaging_providers.dart';
import 'package:livecommerce_mobile/shared/widgets/app_cached_image.dart';
import 'package:livecommerce_mobile/shared/widgets/error_widget.dart';

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
  final _picker = ImagePicker();
  Timer? _pollTimer;
  bool _uploadingImage = false;

  @override
  void initState() {
    super.initState();
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
      final publicUrl = await ref.read(mediaUploadRepositoryProvider).uploadMessageImage(
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
          duration: const Duration(milliseconds: 200),
          curve: Curves.easeOut,
        );
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final chatState = ref.watch(chatNotifierProvider(widget.conversationId));
    final auth = ref.watch(authNotifierProvider);
    final currentUserId = auth.user?.id;
    final busy = chatState.isSending || _uploadingImage;

    ref.listen(chatNotifierProvider(widget.conversationId), (prev, next) {
      if (prev != null && next.messages.length > prev.messages.length) {
        _scrollToBottom();
      }
    });

    final title =
        widget.initialConversation?.participant.displayName ?? l10n.messagesTitle;
    final hasPeer = widget.initialConversation?.participant.id != null;

    return Scaffold(
      appBar: AppBar(
        title: Text(title),
        actions: [
          if (hasPeer)
            PopupMenuButton<String>(
              onSelected: (value) {
                if (value == 'block') {
                  _blockPeer();
                } else if (value == 'unblock') {
                  _unblockPeer();
                }
              },
              itemBuilder: (context) => [
                PopupMenuItem(
                  value: 'block',
                  child: Text(l10n.blockUserAction),
                ),
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
          Expanded(
            child: _buildMessageList(chatState, currentUserId, l10n),
          ),
          _buildInputBar(chatState, l10n, busy),
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
      return Center(
        child: Text(
          l10n.typeMessageHint,
          style: TextStyle(color: Theme.of(context).colorScheme.outline),
        ),
      );
    }

    return ListView.builder(
      controller: _scrollController,
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      itemCount: chatState.messages.length,
      itemBuilder: (context, index) {
        final message = chatState.messages[index];
        final isMe = message.sender.id == currentUserId;
        return _MessageBubble(message: message, isMe: isMe);
      },
    );
  }

  Widget _buildInputBar(ChatState chatState, AppLocalizations l10n, bool busy) {
    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
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
                  : const Icon(Icons.image_outlined),
              tooltip: l10n.attachImage,
            ),
            Expanded(
              child: TextField(
                controller: _textController,
                decoration: InputDecoration(
                  hintText: l10n.typeMessageHint,
                  border: const OutlineInputBorder(
                    borderRadius: BorderRadius.all(Radius.circular(24)),
                  ),
                  contentPadding: const EdgeInsets.symmetric(
                    horizontal: 16,
                    vertical: 10,
                  ),
                ),
                maxLines: 4,
                minLines: 1,
                textInputAction: TextInputAction.newline,
                onSubmitted: (_) => _send(),
              ),
            ),
            const SizedBox(width: 8),
            IconButton.filled(
              onPressed: busy ? null : _send,
              icon: chatState.isSending && !_uploadingImage
                  ? const SizedBox(
                      width: 20,
                      height: 20,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.send),
            ),
          ],
        ),
      ),
    );
  }
}

class _MessageBubble extends StatelessWidget {
  const _MessageBubble({required this.message, required this.isMe});

  final ChatMessage message;
  final bool isMe;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final bgColor = isMe
        ? theme.colorScheme.primary
        : theme.colorScheme.surfaceContainerHighest;
    final fgColor =
        isMe ? theme.colorScheme.onPrimary : theme.colorScheme.onSurface;

    return Align(
      alignment: isMe ? Alignment.centerRight : Alignment.centerLeft,
      child: Container(
        margin: const EdgeInsets.symmetric(vertical: 4),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
        constraints: BoxConstraints(
          maxWidth: MediaQuery.of(context).size.width * 0.72,
        ),
        decoration: BoxDecoration(
          color: bgColor,
          borderRadius: BorderRadius.only(
            topLeft: const Radius.circular(18),
            topRight: const Radius.circular(18),
            bottomLeft: Radius.circular(isMe ? 18 : 4),
            bottomRight: Radius.circular(isMe ? 4 : 18),
          ),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            if (message.imageUrl != null)
              Padding(
                padding: message.body != null && message.body!.isNotEmpty
                    ? const EdgeInsets.only(bottom: 6)
                    : EdgeInsets.zero,
                child: AppCachedImage(
                  url: message.imageUrl!,
                  height: 180,
                  width: double.infinity,
                  borderRadius: BorderRadius.circular(8),
                  errorWidget: Icon(
                    Icons.broken_image_outlined,
                    color: fgColor,
                  ),
                ),
              ),
            if (message.body != null && message.body!.isNotEmpty)
              Text(
                message.body!,
                style: TextStyle(color: fgColor),
              ),
          ],
        ),
      ),
    );
  }
}
