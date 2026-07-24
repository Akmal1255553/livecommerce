import 'package:flutter/material.dart';
import 'package:livecommerce_mobile/core/theme/app_colors.dart';
import 'package:livecommerce_mobile/core/theme/app_dimens.dart';

/// Curated emoji set.
///
/// A hand-picked list keeps the picker dependency-free and fast; a full Unicode
/// table would need an extra package and a searchable index for little gain in
/// a comment box.
abstract final class EmojiCatalog {
  static const List<String> quickReactions = [
    '❤️', '🔥', '😍', '😂', '👏', '🤩', '💯', '🛒',
  ];

  static const Map<String, List<String>> categories = {
    'emotion': [
      '😀', '😃', '😄', '😁', '😆', '😅', '😂', '🤣',
      '🙂', '😊', '😇', '🙃', '😉', '😌', '😍', '🥰',
      '😘', '😗', '😙', '😚', '😋', '😛', '😜', '🤪',
      '🤗', '🤔', '🤨', '😐', '😑', '😶', '🙄', '😏',
      '😴', '🤤', '😪', '😵', '🥳', '😎', '🤓', '🧐',
      '😕', '😟', '🙁', '😢', '😭', '😤', '😠', '🤯',
    ],
    'gesture': [
      '👍', '👎', '👌', '🤌', '✌️', '🤞', '🤟', '🤙',
      '👋', '🙌', '👏', '🤝', '🙏', '💪', '👀', '🫶',
    ],
    'heart': [
      '❤️', '🧡', '💛', '💚', '💙', '💜', '🖤', '🤍',
      '💖', '💗', '💓', '💞', '💕', '💘', '💝', '✨',
      '🔥', '💯', '⭐', '🌟', '💫', '🎉', '🎊', '🏆',
    ],
    'shopping': [
      '🛒', '🛍️', '💰', '💸', '💳', '🎁', '📦', '🚚',
      '👗', '👠', '👜', '💄', '⌚', '📱', '💻', '🎧',
      '🍔', '🍕', '☕', '🍰', '🌸', '🌿', '🏠', '🚀',
    ],
  };

  static const Map<String, IconData> categoryIcons = {
    'emotion': Icons.sentiment_satisfied_alt_outlined,
    'gesture': Icons.back_hand_outlined,
    'heart': Icons.favorite_border_rounded,
    'shopping': Icons.shopping_bag_outlined,
  };
}

/// Horizontal strip of one-tap reactions shown above a composer.
class QuickEmojiBar extends StatelessWidget {
  const QuickEmojiBar({
    super.key,
    required this.onSelected,
    this.emojis = EmojiCatalog.quickReactions,
  });

  final ValueChanged<String> onSelected;
  final List<String> emojis;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 42,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: AppSpacing.lg),
        itemCount: emojis.length,
        separatorBuilder: (_, __) => const SizedBox(width: AppSpacing.sm),
        itemBuilder: (context, index) {
          final emoji = emojis[index];
          return InkResponse(
            onTap: () => onSelected(emoji),
            radius: 22,
            child: Center(
              child: Text(emoji, style: const TextStyle(fontSize: 26)),
            ),
          );
        },
      ),
    );
  }
}

/// Inline emoji grid that replaces the keyboard area when toggled.
class EmojiPickerPanel extends StatefulWidget {
  const EmojiPickerPanel({
    super.key,
    required this.onSelected,
    this.onBackspace,
    this.height = 260,
  });

  final ValueChanged<String> onSelected;
  final VoidCallback? onBackspace;
  final double height;

  @override
  State<EmojiPickerPanel> createState() => _EmojiPickerPanelState();
}

class _EmojiPickerPanelState extends State<EmojiPickerPanel> {
  String _category = EmojiCatalog.categories.keys.first;

  @override
  Widget build(BuildContext context) {
    final palette = AppPalette.of(context);
    final emojis = EmojiCatalog.categories[_category]!;

    return Container(
      height: widget.height,
      decoration: BoxDecoration(
        color: palette.surface,
        border: Border(top: BorderSide(color: palette.outline)),
      ),
      child: Column(
        children: [
          SizedBox(
            height: 44,
            child: Row(
              children: [
                for (final entry in EmojiCatalog.categories.keys)
                  Expanded(
                    child: _CategoryTab(
                      icon: EmojiCatalog.categoryIcons[entry]!,
                      selected: _category == entry,
                      onTap: () => setState(() => _category = entry),
                    ),
                  ),
                if (widget.onBackspace != null)
                  SizedBox(
                    width: 52,
                    child: IconButton(
                      onPressed: widget.onBackspace,
                      icon: const Icon(Icons.backspace_outlined, size: 20),
                      color: palette.textSecondary,
                    ),
                  ),
              ],
            ),
          ),
          Divider(height: 1, color: palette.outline),
          Expanded(
            child: GridView.builder(
              padding: const EdgeInsets.all(AppSpacing.sm),
              gridDelegate: const SliverGridDelegateWithMaxCrossAxisExtent(
                maxCrossAxisExtent: 48,
                mainAxisSpacing: AppSpacing.xs,
                crossAxisSpacing: AppSpacing.xs,
              ),
              itemCount: emojis.length,
              itemBuilder: (context, index) {
                final emoji = emojis[index];
                return InkResponse(
                  onTap: () => widget.onSelected(emoji),
                  radius: 22,
                  child: Center(
                    child: Text(emoji, style: const TextStyle(fontSize: 26)),
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

class _CategoryTab extends StatelessWidget {
  const _CategoryTab({
    required this.icon,
    required this.selected,
    required this.onTap,
  });

  final IconData icon;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final palette = AppPalette.of(context);

    return InkWell(
      onTap: onTap,
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(
            icon,
            size: 22,
            color: selected ? palette.brand : palette.textTertiary,
          ),
          const SizedBox(height: AppSpacing.xs),
          Container(
            height: 2,
            width: selected ? 20 : 0,
            decoration: const BoxDecoration(
              gradient: AppGradients.brand,
              borderRadius: AppRadius.pillAll,
            ),
          ),
        ],
      ),
    );
  }
}
