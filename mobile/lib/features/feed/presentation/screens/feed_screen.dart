import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:livecommerce_mobile/core/l10n/app_localizations.dart';
import 'package:livecommerce_mobile/features/commerce/presentation/providers/commerce_providers.dart';
import 'package:livecommerce_mobile/features/commerce/presentation/widgets/product_overlay_chip.dart';
import 'package:livecommerce_mobile/features/feed/domain/entities/feed_video.dart';
import 'package:livecommerce_mobile/features/feed/domain/entities/product_card.dart';
import 'package:livecommerce_mobile/features/feed/presentation/providers/feed_providers.dart';
import 'package:livecommerce_mobile/features/feed/presentation/providers/feed_video_prefetch.dart';
import 'package:livecommerce_mobile/features/feed/presentation/widgets/feed_video_player.dart';
import 'package:livecommerce_mobile/features/feed/presentation/widgets/video_comments_sheet.dart';
import 'package:livecommerce_mobile/features/moderation/presentation/report_sheet.dart';
import 'package:livecommerce_mobile/shared/widgets/app_cached_image.dart';
import 'package:livecommerce_mobile/shared/widgets/empty_state.dart';
import 'package:livecommerce_mobile/shared/widgets/error_widget.dart';
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
    final cart = ref.watch(cartNotifierProvider);
    final l10n = AppLocalizations.of(context)!;

    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        backgroundColor: Colors.black,
        foregroundColor: Colors.white,
        title: Text(l10n.appTitle),
        actions: [
          IconButton(
            icon: const Icon(Icons.live_tv_outlined),
            tooltip: l10n.liveNowTooltip,
            onPressed: () => context.push('/live'),
          ),
          IconButton(
            icon: Badge(
              isLabelVisible: cart.itemCount > 0,
              label: Text('${cart.itemCount}'),
              child: const Icon(Icons.shopping_cart_outlined),
            ),
            onPressed: () => context.push('/cart'),
          ),
          IconButton(
            icon: const Icon(Icons.receipt_long_outlined),
            onPressed: () => context.push('/orders'),
          ),
          IconButton(
            icon: const Icon(Icons.person_outline),
            onPressed: () => context.push('/profile'),
          ),
        ],
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(48),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              _FeedTabButton(
                label: l10n.feedForYou,
                selected: feed.tab == FeedTab.forYou,
                onTap: () =>
                    ref.read(feedNotifierProvider.notifier).switchTab(FeedTab.forYou),
              ),
              const SizedBox(width: 24),
              _FeedTabButton(
                label: l10n.feedFollowing,
                selected: feed.tab == FeedTab.following,
                onTap: () => ref
                    .read(feedNotifierProvider.notifier)
                    .switchTab(FeedTab.following),
              ),
            ],
          ),
        ),
      ),
      body: _buildBody(context, feed, l10n),
    );
  }

  Widget _buildBody(BuildContext context, FeedState feed, AppLocalizations l10n) {
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
          ref
              .read(feedVideoPrefetchProvider)
              .warmNext(feed.videos, index);
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
        if (isActive &&
            video.videoUrl != null &&
            video.videoUrl!.isNotEmpty) {
          prefetched =
              ref.read(feedVideoPrefetchProvider).claim(video.videoUrl!);
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
      child: Column(
        children: [
          Text(
            label,
            style: TextStyle(
              color: selected ? Colors.white : Colors.white54,
              fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
            ),
          ),
          const SizedBox(height: 8),
          AnimatedContainer(
            duration: const Duration(milliseconds: 200),
            height: 2,
            width: selected ? 48 : 0,
            color: Colors.white,
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

  ProductCard? get _overlayProduct {
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

  Future<void> _toggleBookmark(BuildContext context, WidgetRef ref) async {
    final error =
        await ref.read(feedNotifierProvider.notifier).toggleBookmark(video.id);
    if (error != null && context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error)));
    } else if (context.mounted) {
      final updated = ref
          .read(feedNotifierProvider)
          .videos
          .where((v) => v.id == video.id)
          .firstOrNull;
      final bookmarked = updated?.isBookmarked ?? false;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(bookmarked
              ? AppLocalizations.of(context)!.bookmarkSaved
              : AppLocalizations.of(context)!.bookmarkRemoved),
          duration: const Duration(milliseconds: 900),
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final overlayProduct = _overlayProduct;
    final latest = ref
            .watch(feedNotifierProvider)
            .videos
            .where((v) => v.id == video.id)
            .firstOrNull ??
        video;

    final videoUrl = latest.videoUrl;

    return Stack(
      fit: StackFit.expand,
      children: [
        if (videoUrl != null && videoUrl.isNotEmpty)
          FeedVideoPlayer(
            videoUrl: videoUrl,
            thumbnailUrl: latest.thumbnailUrl,
            isActive: isActive,
            prefetchedController: prefetchedController,
          )
        else
          FeedThumbnailFallback(thumbnailUrl: latest.thumbnailUrl),
        Container(
          decoration: const BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.topCenter,
              end: Alignment.bottomCenter,
              colors: [Colors.transparent, Colors.black54],
            ),
          ),
        ),
        if (overlayProduct != null)
          Positioned(
            left: 16,
            right: 96,
            bottom: 140,
            child: ProductOverlayChip(product: overlayProduct),
          ),
        Positioned(
          left: 16,
          right: 80,
          bottom: 32,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                '@${latest.user.username}',
                style: const TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w700,
                  fontSize: 16,
                ),
              ),
              if (latest.title != null && latest.title!.isNotEmpty) ...[
                const SizedBox(height: 8),
                Text(
                  latest.title!,
                  style: const TextStyle(color: Colors.white, fontSize: 15),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
              ],
              if (latest.description != null &&
                  latest.description!.isNotEmpty) ...[
                const SizedBox(height: 4),
                Text(
                  latest.description!,
                  style: const TextStyle(color: Colors.white70, fontSize: 13),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
              ],
            ],
          ),
        ),
        Positioned(
          right: 16,
          bottom: 32,
          child: Column(
            children: [
              AppCachedAvatar(
                url: latest.user.avatarUrl,
                radius: 20,
              ),
              const SizedBox(height: 20),
              _SideAction(
                icon: latest.isLiked ? Icons.favorite : Icons.favorite_border,
                label: '${latest.likeCount}',
                activeColor: latest.isLiked ? Colors.redAccent : null,
                onTap: () => _toggleLike(context, ref),
              ),
              const SizedBox(height: 14),
              _SideAction(
                icon: Icons.chat_bubble_outline,
                label: '${latest.commentCount}',
                onTap: () => showVideoCommentsSheet(
                  context: context,
                  ref: ref,
                  videoId: latest.id,
                ),
              ),
              const SizedBox(height: 14),
              _SideAction(
                icon: latest.isBookmarked
                    ? Icons.bookmark
                    : Icons.bookmark_border,
                label: AppLocalizations.of(context)!.saveAction,
                activeColor: latest.isBookmarked ? Colors.amber : null,
                onTap: () => _toggleBookmark(context, ref),
              ),
              const SizedBox(height: 14),
              _SideAction(
                icon: Icons.flag_outlined,
                label: AppLocalizations.of(context)!.reportAction,
                onTap: () => showReportSheet(
                  context: context,
                  ref: ref,
                  targetType: 'video',
                  targetId: latest.id,
                ),
              ),
              const SizedBox(height: 14),
              _SideAction(
                icon: Icons.remove_red_eye_outlined,
                label: '${latest.viewCount}',
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _SideAction extends StatelessWidget {
  const _SideAction({
    required this.icon,
    required this.label,
    this.onTap,
    this.activeColor,
  });

  final IconData icon;
  final String label;
  final VoidCallback? onTap;
  final Color? activeColor;

  @override
  Widget build(BuildContext context) {
    final child = Column(
      children: [
        AnimatedScale(
          scale: activeColor != null ? 1.08 : 1,
          duration: const Duration(milliseconds: 160),
          child: Icon(icon, color: activeColor ?? Colors.white, size: 28),
        ),
        const SizedBox(height: 4),
        Text(label, style: const TextStyle(color: Colors.white, fontSize: 12)),
      ],
    );

    if (onTap == null) {
      return child;
    }

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(24),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 4, horizontal: 6),
        child: child,
      ),
    );
  }
}
