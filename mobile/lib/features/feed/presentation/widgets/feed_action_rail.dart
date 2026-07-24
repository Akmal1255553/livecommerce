import 'package:flutter/material.dart';
import 'package:livecommerce_mobile/core/theme/app_colors.dart';
import 'package:livecommerce_mobile/core/theme/app_dimens.dart';
import 'package:livecommerce_mobile/core/theme/app_typography.dart';
import 'package:livecommerce_mobile/core/utils/number_format.dart';
import 'package:livecommerce_mobile/shared/widgets/app_cached_image.dart';

/// Vertical rail of engagement actions on the right edge of a feed video.
class FeedActionRail extends StatelessWidget {
  const FeedActionRail({
    super.key,
    required this.avatarUrl,
    required this.likeCount,
    required this.commentCount,
    required this.isLiked,
    required this.isBookmarked,
    required this.onLike,
    required this.onComment,
    required this.onBookmark,
    required this.onShare,
    required this.onMore,
    this.onAvatarTap,
    this.showFollowBadge = true,
  });

  final String? avatarUrl;
  final int likeCount;
  final int commentCount;
  final bool isLiked;
  final bool isBookmarked;
  final VoidCallback onLike;
  final VoidCallback onComment;
  final VoidCallback onBookmark;
  final VoidCallback onShare;
  final VoidCallback onMore;
  final VoidCallback? onAvatarTap;
  final bool showFollowBadge;

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        _RailAvatar(
          url: avatarUrl,
          onTap: onAvatarTap,
          showFollowBadge: showFollowBadge,
        ),
        const SizedBox(height: AppSpacing.xxl),
        FeedActionButton(
          icon: isLiked ? Icons.favorite_rounded : Icons.favorite_border_rounded,
          label: formatCount(likeCount),
          active: isLiked,
          activeColor: AppColors.brandPink,
          onTap: onLike,
        ),
        const SizedBox(height: AppSpacing.lg),
        FeedActionButton(
          icon: Icons.mode_comment_outlined,
          label: formatCount(commentCount),
          onTap: onComment,
        ),
        const SizedBox(height: AppSpacing.lg),
        FeedActionButton(
          icon: isBookmarked
              ? Icons.bookmark_rounded
              : Icons.bookmark_border_rounded,
          label: '',
          active: isBookmarked,
          activeColor: AppColors.saved,
          onTap: onBookmark,
        ),
        const SizedBox(height: AppSpacing.lg),
        FeedActionButton(
          icon: Icons.reply_rounded,
          label: '',
          flipIcon: true,
          onTap: onShare,
        ),
        const SizedBox(height: AppSpacing.lg),
        FeedActionButton(
          icon: Icons.more_horiz_rounded,
          label: '',
          onTap: onMore,
        ),
      ],
    );
  }
}

/// Circular icon button with an optional counter, sized for one-thumb reach.
class FeedActionButton extends StatefulWidget {
  const FeedActionButton({
    super.key,
    required this.icon,
    required this.label,
    required this.onTap,
    this.active = false,
    this.activeColor,
    this.flipIcon = false,
  });

  final IconData icon;
  final String label;
  final VoidCallback onTap;
  final bool active;
  final Color? activeColor;
  final bool flipIcon;

  @override
  State<FeedActionButton> createState() => _FeedActionButtonState();
}

class _FeedActionButtonState extends State<FeedActionButton>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;
  late final Animation<double> _scale;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 320),
    );
    _scale = TweenSequence<double>([
      TweenSequenceItem(tween: Tween(begin: 1.0, end: 1.35), weight: 40),
      TweenSequenceItem(tween: Tween(begin: 1.35, end: 0.92), weight: 25),
      TweenSequenceItem(tween: Tween(begin: 0.92, end: 1.0), weight: 35),
    ]).animate(CurvedAnimation(parent: _controller, curve: Curves.easeOut));
  }

  @override
  void didUpdateWidget(covariant FeedActionButton oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.active && !oldWidget.active) {
      _controller.forward(from: 0);
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final color =
        widget.active ? (widget.activeColor ?? AppColors.brandPink) : Colors.white;

    Widget icon = Icon(widget.icon, size: 30, color: color, shadows: const [
      Shadow(color: Color(0x73000000), blurRadius: 8, offset: Offset(0, 1)),
    ]);
    if (widget.flipIcon) {
      icon = Transform.flip(flipX: true, child: icon);
    }

    return GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTap: widget.onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: AppSpacing.sm),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ScaleTransition(scale: _scale, child: icon),
            if (widget.label.isNotEmpty) ...[
              const SizedBox(height: AppSpacing.xs + 2),
              Text(
                widget.label,
                style: AppTypography.numeric(
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                  color: Colors.white,
                  letterSpacing: 0,
                ).copyWith(shadows: AppTypography.mediaShadow),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _RailAvatar extends StatelessWidget {
  const _RailAvatar({
    required this.url,
    required this.onTap,
    required this.showFollowBadge,
  });

  final String? url;
  final VoidCallback? onTap;
  final bool showFollowBadge;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: SizedBox(
        width: 52,
        height: showFollowBadge ? 62 : 52,
        child: Stack(
          clipBehavior: Clip.none,
          alignment: Alignment.topCenter,
          children: [
            Container(
              width: 50,
              height: 50,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                border: Border.all(color: Colors.white, width: 2),
              ),
              child: ClipOval(child: AppCachedAvatar(url: url, radius: 23)),
            ),
            if (showFollowBadge)
              Positioned(
                bottom: 0,
                child: Container(
                  width: 22,
                  height: 22,
                  decoration: const BoxDecoration(
                    gradient: AppGradients.brand,
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(Icons.add, size: 15, color: Colors.white),
                ),
              ),
          ],
        ),
      ),
    );
  }
}
