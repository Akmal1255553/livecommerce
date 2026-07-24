import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:livecommerce_mobile/core/l10n/app_localizations.dart';
import 'package:livecommerce_mobile/core/theme/app_colors.dart';
import 'package:livecommerce_mobile/core/theme/app_dimens.dart';
import 'package:livecommerce_mobile/core/theme/app_theme.dart';
import 'package:livecommerce_mobile/core/theme/app_typography.dart';
import 'package:livecommerce_mobile/core/utils/number_format.dart';
import 'package:livecommerce_mobile/features/commerce/presentation/providers/commerce_providers.dart';
import 'package:livecommerce_mobile/features/commerce/presentation/widgets/product_overlay_chip.dart';
import 'package:livecommerce_mobile/features/feed/domain/entities/feed_video.dart';
import 'package:livecommerce_mobile/features/feed/domain/entities/product_card.dart';
import 'package:livecommerce_mobile/features/feed/presentation/providers/feed_providers.dart';
import 'package:livecommerce_mobile/features/feed/presentation/providers/feed_video_prefetch.dart';
import 'package:livecommerce_mobile/features/feed/presentation/widgets/double_tap_like.dart';
import 'package:livecommerce_mobile/features/feed/presentation/widgets/feed_action_rail.dart';
import 'package:livecommerce_mobile/features/feed/presentation/widgets/feed_video_player.dart';
import 'package:livecommerce_mobile/features/feed/presentation/widgets/video_comments_sheet.dart';
import 'package:livecommerce_mobile/features/moderation/presentation/report_sheet.dart';
import 'package:livecommerce_mobile/shared/widgets/app_bottom_nav.dart';
import 'package:livecommerce_mobile/shared/widgets/empty_state.dart';
import 'package:livecommerce_mobile/shared/widgets/error_widget.dart';
import 'package:livecommerce_mobile/shared/widgets/glass_panel.dart';
import 'package:livecommerce_mobile/shared/widgets/skeleton.dart';
import 'package:video_player/video_player.dart';

class FeedScreen extends ConsumerStatefulWidget {
  const FeedScreen({super.key});

  @override
  ConsumerState<FeedScreen> createState() => _FeedScreenState();
}

class _FeedScreenState extends ConsumerState<FeedScreen> {
  late final PageController _pageController;
  int _currentPage = 0;

  @override
  void initState() {
    super.initState();
    _pageController = PageController();
    Future.microtask(() {
      // Keep in-memory likes/comments when returning to Home; only fetch if empty.
      final feed = ref.read(feedNotifierProvider);
      if (feed.videos.isEmpty && !feed.isLoading) {
        ref.read(feedNotifierProvider.notifier).load();
      }
      ref.read(cartNotifierProvider.notifier).load();
    });
  }

  @override
  void dispose() {
    _pageController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final feed = ref.watch(feedNotifierProvider);
    final l10n = AppLocalizations.of(context)!;

    return AppTheme.immersive(
      child: AnnotatedRegion<SystemUiOverlayStyle>(
        value: AppTheme.immersiveOverlay,
        child: Scaffold(
          backgroundColor: Colors.black,
          extendBody: true,
          extendBodyBehindAppBar: true,
          body: Stack(
            children: [
              Positioned.fill(child: _buildBody(context, feed, l10n)),
              Positioned(
                top: 0,
                left: 0,
                right: 0,
                child: _FeedTopBar(
                  tab: feed.tab,
                  onTabChanged: (tab) =>
                      ref.read(feedNotifierProvider.notifier).switchTab(tab),
                ),
              ),
              const Positioned(
                left: 0,
                right: 0,
                bottom: 0,
                child: AppBottomNav(current: AppTab.home, transparent: true),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildBody(
    BuildContext context,
    FeedState feed,
    AppLocalizations l10n,
  ) {
    if (feed.isLoading && feed.videos.isEmpty) {
      return const FeedSkeleton();
    }

    if (feed.error != null && feed.videos.isEmpty) {
      return ErrorDisplay(
        message: feed.error!,
        foregroundColor: Colors.white70,
        onRetry: () => ref.read(feedNotifierProvider.notifier).load(),
      );
    }

    if (feed.videos.isEmpty) {
      return EmptyState(
        title: feed.tab == FeedTab.following
            ? l10n.feedFollowingEmptyTitle
            : l10n.feedEmptyTitle,
        subtitle: feed.tab == FeedTab.following
            ? l10n.feedFollowingEmptySubtitle
            : l10n.feedEmptySubtitle,
        icon: Icons.video_library_outlined,
        foregroundColor: Colors.white,
        actionLabel: l10n.refresh,
        onAction: () => ref.read(feedNotifierProvider.notifier).load(),
      );
    }

    return PageView.builder(
      controller: _pageController,
      scrollDirection: Axis.vertical,
      itemCount: feed.videos.length + (feed.hasMore ? 1 : 0),
      onPageChanged: (index) {
        setState(() => _currentPage = index);
        if (index < feed.videos.length) {
          ref.read(feedNotifierProvider.notifier).recordView(feed.videos[index].id);
          ref.read(feedVideoPrefetchProvider).warmNext(feed.videos, index);
        }
        if (feed.hasMore && index >= feed.videos.length - 2) {
          ref.read(feedNotifierProvider.notifier).loadMore();
        }
      },
      itemBuilder: (context, index) {
        if (index >= feed.videos.length) {
          return const Center(
            child: CircularProgressIndicator(color: Colors.white),
          );
        }

        final video = feed.videos[index];
        final isActive = index == _currentPage;
        VideoPlayerController? prefetched;
        if (isActive && video.videoUrl != null && video.videoUrl!.isNotEmpty) {
          prefetched = ref.read(feedVideoPrefetchProvider).claim(video.videoUrl!);
        }

        // Warm next while building the first active page.
        if (isActive && index == 0) {
          WidgetsBinding.instance.addPostFrameCallback((_) {
            ref.read(feedVideoPrefetchProvider).warmNext(feed.videos, index);
          });
        }

        return _FeedVideoPage(
          video: video,
          isActive: isActive,
          prefetchedController: prefetched,
        );
      },
    );
  }
}

class _FeedTopBar extends ConsumerWidget {
  const _FeedTopBar({required this.tab, required this.onTabChanged});

  final FeedTab tab;
  final ValueChanged<FeedTab> onTabChanged;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context)!;
    final cart = ref.watch(cartNotifierProvider);

    return DecoratedBox(
      decoration: const BoxDecoration(gradient: AppGradients.topScrim),
      child: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(
            AppSpacing.md,
            AppSpacing.sm,
            AppSpacing.md,
            AppSpacing.xxl,
          ),
          child: Row(
            children: [
              GestureDetector(
                onTap: () => context.push('/live'),
                child: const MediaPill(
                  label: 'LIVE',
                  gradient: AppGradients.live,
                  dot: true,
                ),
              ),
              Expanded(
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    _FeedTabButton(
                      label: l10n.feedFollowing,
                      selected: tab == FeedTab.following,
                      onTap: () => onTabChanged(FeedTab.following),
                    ),
                    const SizedBox(width: AppSpacing.xl),
                    _FeedTabButton(
                      label: l10n.feedForYou,
                      selected: tab == FeedTab.forYou,
                      onTap: () => onTabChanged(FeedTab.forYou),
                    ),
                  ],
                ),
              ),
              _CartButton(count: cart.itemCount),
            ],
          ),
        ),
      ),
    );
  }
}

class _CartButton extends StatelessWidget {
  const _CartButton({required this.count});

  final int count;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () => context.push('/cart'),
      behavior: HitTestBehavior.opaque,
      child: SizedBox(
        width: 44,
        height: 44,
        child: Stack(
          alignment: Alignment.center,
          children: [
            const Icon(
              Icons.shopping_bag_outlined,
              color: Colors.white,
              size: 26,
              shadows: [
                Shadow(color: Color(0x73000000), blurRadius: 8),
              ],
            ),
            if (count > 0)
              Positioned(
                top: 2,
                right: 2,
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1),
                  constraints: const BoxConstraints(minWidth: 18),
                  decoration: BoxDecoration(
                    gradient: AppGradients.brand,
                    borderRadius: AppRadius.pillAll,
                    border: Border.all(color: Colors.black26),
                  ),
                  child: Text(
                    count > 99 ? '99+' : '$count',
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 10,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _FeedTabButton extends StatelessWidget {
  const _FeedTabButton({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      behavior: HitTestBehavior.opaque,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            label,
            style: TextStyle(
              color: selected ? Colors.white : Colors.white60,
              fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
              fontSize: 16,
              shadows: AppTypography.mediaShadow,
            ),
          ),
          const SizedBox(height: AppSpacing.sm),
          AnimatedContainer(
            duration: AppDuration.fast,
            curve: AppDuration.curve,
            height: 3,
            width: selected ? 28 : 0,
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

class _FeedVideoPage extends ConsumerWidget {
  const _FeedVideoPage({
    required this.video,
    required this.isActive,
    this.prefetchedController,
  });

  final FeedVideo video;
  final bool isActive;
  final VideoPlayerController? prefetchedController;

  ProductCard? _overlayProduct(FeedVideo video) {
    if (video.products.isEmpty) {
      return null;
    }
    final featured = video.products.where((tag) => tag.isFeatured).toList();
    if (featured.isNotEmpty) {
      return featured.first.product;
    }
    return video.products.first.product;
  }

  Future<void> _toggleLike(BuildContext context, WidgetRef ref) async {
    final error =
        await ref.read(feedNotifierProvider.notifier).toggleLike(video.id);
    if (error != null && context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error)));
    }
  }

  Future<void> _likeFromDoubleTap(BuildContext context, WidgetRef ref) async {
    final current = ref
        .read(feedNotifierProvider)
        .videos
        .where((v) => v.id == video.id)
        .firstOrNull;
    // Double tap always likes, never unlikes.
    if (current?.isLiked ?? false) {
      return;
    }
    await _toggleLike(context, ref);
  }

  Future<void> _toggleBookmark(BuildContext context, WidgetRef ref) async {
    final error =
        await ref.read(feedNotifierProvider.notifier).toggleBookmark(video.id);
    if (!context.mounted) {
      return;
    }
    if (error != null) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error)));
      return;
    }
    final updated = ref
        .read(feedNotifierProvider)
        .videos
        .where((v) => v.id == video.id)
        .firstOrNull;
    final bookmarked = updated?.isBookmarked ?? false;
    final l10n = AppLocalizations.of(context)!;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(bookmarked ? l10n.bookmarkSaved : l10n.bookmarkRemoved),
        duration: const Duration(milliseconds: 900),
      ),
    );
  }

  Future<void> _share(BuildContext context) async {
    await Clipboard.setData(
      ClipboardData(text: 'https://livecommerce.uz/v/${video.id}'),
    );
    if (!context.mounted) {
      return;
    }
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(AppLocalizations.of(context)!.linkCopied),
        duration: const Duration(milliseconds: 1200),
      ),
    );
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context)!;
    final latest = ref
            .watch(feedNotifierProvider)
            .videos
            .where((v) => v.id == video.id)
            .firstOrNull ??
        video;

    final videoUrl = latest.videoUrl;
    final overlayProduct = _overlayProduct(latest);
    final bottomInset = AppBottomNav.reservedSpace(context);

    return Stack(
      fit: StackFit.expand,
      children: [
        DoubleTapLike(
          onLike: () => _likeFromDoubleTap(context, ref),
          child: videoUrl != null && videoUrl.isNotEmpty
              ? FeedVideoPlayer(
                  videoUrl: videoUrl,
                  thumbnailUrl: latest.thumbnailUrl,
                  isActive: isActive,
                  prefetchedController: prefetchedController,
                )
              : FeedThumbnailFallback(thumbnailUrl: latest.thumbnailUrl),
        ),
        const IgnorePointer(
          child: DecoratedBox(
            decoration: BoxDecoration(gradient: AppGradients.bottomScrim),
            child: SizedBox.expand(),
          ),
        ),
        Positioned(
          right: AppSpacing.sm,
          bottom: bottomInset,
          child: FeedActionRail(
            avatarUrl: latest.user.avatarUrl,
            likeCount: latest.likeCount,
            commentCount: latest.commentCount,
            isLiked: latest.isLiked,
            isBookmarked: latest.isBookmarked,
            onLike: () => _toggleLike(context, ref),
            onComment: () => showVideoCommentsSheet(
              context: context,
              ref: ref,
              videoId: latest.id,
            ),
            onBookmark: () => _toggleBookmark(context, ref),
            onShare: () => _share(context),
            onMore: () => showReportSheet(
              context: context,
              ref: ref,
              targetType: 'video',
              targetId: latest.id,
            ),
          ),
        ),
        Positioned(
          left: AppSpacing.lg,
          right: 84,
          bottom: bottomInset,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              if (overlayProduct != null) ...[
                ProductOverlayChip(
                  product: overlayProduct,
                  actionLabel: l10n.buyNow,
                ),
                const SizedBox(height: AppSpacing.md),
              ],
              _VideoCaption(video: latest),
            ],
          ),
        ),
      ],
    );
  }
}

class _VideoCaption extends StatelessWidget {
  const _VideoCaption({required this.video});

  final FeedVideo video;

  @override
  Widget build(BuildContext context) {
    final shadows = AppTypography.mediaShadow;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Flexible(
              child: Text(
                '@${video.user.username}',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w700,
                  fontSize: 16,
                  shadows: shadows,
                ),
              ),
            ),
            if (video.user.isVerified) ...[
              const SizedBox(width: AppSpacing.xs),
              const Icon(Icons.verified, size: 16, color: AppColors.info),
            ],
            const SizedBox(width: AppSpacing.sm),
            Icon(
              Icons.visibility_outlined,
              size: 14,
              color: Colors.white.withValues(alpha: 0.7),
            ),
            const SizedBox(width: AppSpacing.xxs + 2),
            Text(
              formatCount(video.viewCount),
              style: TextStyle(
                color: Colors.white.withValues(alpha: 0.7),
                fontSize: 12,
                fontWeight: FontWeight.w600,
                shadows: shadows,
              ),
            ),
          ],
        ),
        if (video.title != null && video.title!.isNotEmpty) ...[
          const SizedBox(height: AppSpacing.sm),
          Text(
            video.title!,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              color: Colors.white,
              fontSize: 15,
              height: 1.35,
              fontWeight: FontWeight.w500,
              shadows: shadows,
            ),
          ),
        ],
        if (video.description != null && video.description!.isNotEmpty) ...[
          const SizedBox(height: AppSpacing.xs),
          Text(
            video.description!,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              color: Colors.white.withValues(alpha: 0.78),
              fontSize: 13,
              height: 1.35,
              shadows: shadows,
            ),
          ),
        ],
      ],
    );
  }
}
