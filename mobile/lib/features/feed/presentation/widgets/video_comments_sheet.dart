import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:livecommerce_mobile/core/errors/error_handler.dart';
import 'package:livecommerce_mobile/core/l10n/app_localizations.dart';
import 'package:livecommerce_mobile/core/theme/app_colors.dart';
import 'package:livecommerce_mobile/core/theme/app_dimens.dart';
import 'package:livecommerce_mobile/core/utils/time_format.dart';
import 'package:livecommerce_mobile/features/feed/domain/entities/feed_video.dart';
import 'package:livecommerce_mobile/features/feed/presentation/providers/feed_providers.dart';
import 'package:livecommerce_mobile/shared/widgets/emoji_picker.dart';
import 'package:livecommerce_mobile/shared/widgets/empty_state.dart';
import 'package:livecommerce_mobile/shared/widgets/error_widget.dart';
import 'package:livecommerce_mobile/shared/widgets/skeleton.dart';
import 'package:livecommerce_mobile/shared/widgets/user_avatar.dart';

Future<void> showVideoCommentsSheet({
  required BuildContext context,
  required WidgetRef ref,
  required String videoId,
}) {
  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    showDragHandle: true,
    useSafeArea: true,
    builder: (context) => _CommentsSheet(videoId: videoId),
  );
}

class _CommentsSheet extends ConsumerStatefulWidget {
  const _CommentsSheet({required this.videoId});

  final String videoId;

  @override
  ConsumerState<_CommentsSheet> createState() => _CommentsSheetState();
}

class _CommentsSheetState extends ConsumerState<_CommentsSheet> {
  final _controller = TextEditingController();
  final _focus = FocusNode();
  List<VideoComment> _comments = const [];
  bool _loading = true;
  bool _posting = false;
  bool _emojiOpen = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _focus.addListener(() {
      if (_focus.hasFocus && _emojiOpen) {
        setState(() => _emojiOpen = false);
      }
    });
    Future.microtask(_load);
  }

  @override
  void dispose() {
    _controller.dispose();
    _focus.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final comments =
          await ref.read(feedRepositoryProvider).fetchComments(widget.videoId);
      if (!mounted) {
        return;
      }
      setState(() {
        _comments = comments;
        _loading = false;
      });
    } catch (error) {
      if (!mounted) {
        return;
      }
      setState(() {
        _loading = false;
        _error = describeFailure(error);
      });
    }
  }

  void _insert(String emoji) {
    final selection = _controller.selection;
    final text = _controller.text;
    // Selection is invalid until the field has been focused at least once.
    final start = selection.start < 0 ? text.length : selection.start;
    final end = selection.end < 0 ? text.length : selection.end;

    _controller.value = TextEditingValue(
      text: text.replaceRange(start, end, emoji),
      selection: TextSelection.collapsed(offset: start + emoji.length),
    );
    setState(() {});
  }

  void _backspace() {
    final text = _controller.text;
    if (text.isEmpty) {
      return;
    }
    // Trim by grapheme so multi-codepoint emoji disappear in one tap.
    final trimmed = text.characters.skipLast(1).toString();
    _controller.value = TextEditingValue(
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

  Future<void> _submit() async {
    final body = _controller.text.trim();
    if (body.isEmpty || _posting) {
      return;
    }

    setState(() => _posting = true);
    try {
      final comment = await ref.read(feedRepositoryProvider).postComment(
            videoId: widget.videoId,
            body: body,
          );
      if (!mounted) {
        return;
      }
      setState(() {
        _comments = [comment, ..._comments];
        _controller.clear();
        _posting = false;
      });
      HapticFeedback.selectionClick();
      ref
          .read(feedNotifierProvider.notifier)
          .bumpCommentCount(widget.videoId);
    } catch (error) {
      if (!mounted) {
        return;
      }
      setState(() => _posting = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(describeFailure(error))),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final palette = AppPalette.of(context);
    final viewInsets = MediaQuery.viewInsetsOf(context).bottom;
    final maxHeight = MediaQuery.sizeOf(context).height * 0.82;

    return AnimatedPadding(
      duration: AppDuration.fast,
      curve: AppDuration.curve,
      padding: EdgeInsets.only(bottom: viewInsets),
      child: ConstrainedBox(
        constraints: BoxConstraints(maxHeight: maxHeight),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(
                AppSpacing.lg,
                0,
                AppSpacing.sm,
                AppSpacing.sm,
              ),
              child: Row(
                children: [
                  Text(
                    l10n.commentsTitle,
                    style: Theme.of(context)
                        .textTheme
                        .titleMedium
                        ?.copyWith(fontWeight: FontWeight.w700),
                  ),
                  if (_comments.isNotEmpty) ...[
                    const SizedBox(width: AppSpacing.sm),
                    Text(
                      '${_comments.length}',
                      style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                            color: palette.textTertiary,
                          ),
                    ),
                  ],
                  const Spacer(),
                  IconButton(
                    tooltip: l10n.cancelButton,
                    onPressed: () => Navigator.of(context).pop(),
                    icon: const Icon(Icons.close_rounded),
                  ),
                ],
              ),
            ),
            Divider(height: 1, color: palette.outline),
            Flexible(child: _buildList(context, l10n)),
            Divider(height: 1, color: palette.outline),
            QuickEmojiBar(onSelected: _insert),
            _Composer(
              controller: _controller,
              focusNode: _focus,
              posting: _posting,
              emojiOpen: _emojiOpen,
              hint: l10n.commentsHint,
              onToggleEmoji: _toggleEmoji,
              onSubmit: _submit,
            ),
            if (_emojiOpen)
              EmojiPickerPanel(onSelected: _insert, onBackspace: _backspace)
            else
              SizedBox(height: MediaQuery.paddingOf(context).bottom),
          ],
        ),
      ),
    );
  }

  Widget _buildList(BuildContext context, AppLocalizations l10n) {
    if (_loading) {
      return const SizedBox(height: 280, child: ListSkeleton(itemCount: 4));
    }
    if (_error != null) {
      return SizedBox(
        height: 240,
        child: ErrorDisplay(message: _error!, onRetry: _load),
      );
    }
    if (_comments.isEmpty) {
      return SizedBox(
        height: 240,
        child: EmptyState(
          title: l10n.commentsEmptyTitle,
          subtitle: l10n.commentsEmptySubtitle,
          icon: Icons.mode_comment_outlined,
        ),
      );
    }

    return ListView.separated(
      padding: const EdgeInsets.symmetric(
        horizontal: AppSpacing.lg,
        vertical: AppSpacing.md,
      ),
      shrinkWrap: true,
      itemCount: _comments.length,
      separatorBuilder: (_, __) => const SizedBox(height: AppSpacing.lg),
      itemBuilder: (context, index) => _CommentTile(comment: _comments[index]),
    );
  }
}

class _CommentTile extends StatelessWidget {
  const _CommentTile({required this.comment});

  final VideoComment comment;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final palette = AppPalette.of(context);
    final texts = Theme.of(context).textTheme;
    final time = formatTimeAgo(l10n, comment.createdAt);

    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        UserAvatar(
          username: comment.username,
          avatarUrl: comment.avatarUrl,
          radius: 18,
        ),
        const SizedBox(width: AppSpacing.md),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Flexible(
                    child: Text(
                      '@${comment.username}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: texts.labelLarge?.copyWith(
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                  if (time.isNotEmpty) ...[
                    const SizedBox(width: AppSpacing.sm),
                    Text(
                      time,
                      style: texts.bodySmall?.copyWith(
                        color: palette.textTertiary,
                      ),
                    ),
                  ],
                ],
              ),
              const SizedBox(height: AppSpacing.xs),
              Text(
                comment.body,
                style: texts.bodyMedium?.copyWith(height: 1.35),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _Composer extends StatelessWidget {
  const _Composer({
    required this.controller,
    required this.focusNode,
    required this.posting,
    required this.emojiOpen,
    required this.hint,
    required this.onToggleEmoji,
    required this.onSubmit,
  });

  final TextEditingController controller;
  final FocusNode focusNode;
  final bool posting;
  final bool emojiOpen;
  final String hint;
  final VoidCallback onToggleEmoji;
  final VoidCallback onSubmit;

  @override
  Widget build(BuildContext context) {
    final palette = AppPalette.of(context);

    return Padding(
      padding: const EdgeInsets.fromLTRB(
        AppSpacing.lg,
        AppSpacing.sm,
        AppSpacing.md,
        AppSpacing.md,
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          Expanded(
            child: TextField(
              controller: controller,
              focusNode: focusNode,
              minLines: 1,
              maxLines: 4,
              textInputAction: TextInputAction.send,
              onSubmitted: (_) => onSubmit(),
              decoration: InputDecoration(
                hintText: hint,
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
                  onPressed: onToggleEmoji,
                  icon: Icon(
                    emojiOpen
                        ? Icons.keyboard_alt_outlined
                        : Icons.emoji_emotions_outlined,
                    color: emojiOpen ? palette.brand : palette.textSecondary,
                  ),
                ),
              ),
            ),
          ),
          const SizedBox(width: AppSpacing.sm),
          _SendButton(posting: posting, onTap: onSubmit),
        ],
      ),
    );
  }
}

class _SendButton extends StatelessWidget {
  const _SendButton({required this.posting, required this.onTap});

  final bool posting;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: posting ? null : onTap,
      child: Container(
        width: 46,
        height: 46,
        decoration: BoxDecoration(
          gradient: AppGradients.brand,
          shape: BoxShape.circle,
          boxShadow: AppShadows.brandGlow(opacity: 0.28),
        ),
        child: posting
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
