import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:livecommerce_mobile/features/commerce/presentation/providers/commerce_providers.dart';
import 'package:livecommerce_mobile/features/commerce/presentation/widgets/product_overlay_chip.dart';
import 'package:livecommerce_mobile/features/feed/domain/entities/feed_video.dart';
import 'package:livecommerce_mobile/features/feed/domain/entities/product_card.dart';
import 'package:livecommerce_mobile/features/feed/presentation/providers/feed_providers.dart';

class FeedScreen extends ConsumerStatefulWidget {
  const FeedScreen({super.key});

  @override
  ConsumerState<FeedScreen> createState() => _FeedScreenState();
}

class _FeedScreenState extends ConsumerState<FeedScreen> {
  late final PageController _pageController;

  @override
  void initState() {
    super.initState();
    _pageController = PageController();
    Future.microtask(() {
      ref.read(feedNotifierProvider.notifier).load();
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

    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        backgroundColor: Colors.black,
        foregroundColor: Colors.white,
        title: const Text('LiveCommerce'),
        actions: [
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
                label: 'For You',
                selected: feed.tab == FeedTab.forYou,
                onTap: () =>
                    ref.read(feedNotifierProvider.notifier).switchTab(FeedTab.forYou),
              ),
              const SizedBox(width: 24),
              _FeedTabButton(
                label: 'Following',
                selected: feed.tab == FeedTab.following,
                onTap: () => ref
                    .read(feedNotifierProvider.notifier)
                    .switchTab(FeedTab.following),
              ),
            ],
          ),
        ),
      ),
      body: _buildBody(feed),
    );
  }

  Widget _buildBody(FeedState feed) {
    if (feed.isLoading && feed.videos.isEmpty) {
      return const Center(child: CircularProgressIndicator(color: Colors.white));
    }

    if (feed.error != null && feed.videos.isEmpty) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Text(
                feed.error!,
                style: const TextStyle(color: Colors.white70),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 16),
              FilledButton(
                onPressed: () => ref.read(feedNotifierProvider.notifier).load(),
                child: const Text('Retry'),
              ),
            ],
          ),
        ),
      );
    }

    if (feed.videos.isEmpty) {
      return Center(
        child: Text(
          feed.tab == FeedTab.following
              ? 'Follow creators to see their videos here.'
              : 'No videos yet.',
          style: const TextStyle(color: Colors.white70),
          textAlign: TextAlign.center,
        ),
      );
    }

    return PageView.builder(
      controller: _pageController,
      scrollDirection: Axis.vertical,
      itemCount: feed.videos.length + (feed.hasMore ? 1 : 0),
      onPageChanged: (index) {
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

        return _FeedVideoPage(video: feed.videos[index]);
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

class _FeedVideoPage extends StatelessWidget {
  const _FeedVideoPage({required this.video});

  final FeedVideo video;

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

  @override
  Widget build(BuildContext context) {
    final overlayProduct = _overlayProduct;

    return Stack(
      fit: StackFit.expand,
      children: [
        if (video.thumbnailUrl != null)
          Image.network(
            video.thumbnailUrl!,
            fit: BoxFit.cover,
            errorBuilder: (_, __, ___) => const ColoredBox(color: Color(0xFF1A1A1A)),
          )
        else
          const ColoredBox(color: Color(0xFF1A1A1A)),
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
                '@${video.user.username}',
                style: const TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w700,
                  fontSize: 16,
                ),
              ),
              if (video.title != null && video.title!.isNotEmpty) ...[
                const SizedBox(height: 8),
                Text(
                  video.title!,
                  style: const TextStyle(color: Colors.white, fontSize: 15),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
              ],
              if (video.description != null && video.description!.isNotEmpty) ...[
                const SizedBox(height: 4),
                Text(
                  video.description!,
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
              CircleAvatar(
                backgroundImage: video.user.avatarUrl != null
                    ? NetworkImage(video.user.avatarUrl!)
                    : null,
                child: video.user.avatarUrl == null
                    ? Text(video.user.username.characters.first.toUpperCase())
                    : null,
              ),
              const SizedBox(height: 24),
              _SideAction(icon: Icons.favorite_border, label: '${video.likeCount}'),
              const SizedBox(height: 16),
              _SideAction(icon: Icons.chat_bubble_outline, label: '${video.commentCount}'),
              const SizedBox(height: 16),
              _SideAction(icon: Icons.remove_red_eye_outlined, label: '${video.viewCount}'),
            ],
          ),
        ),
      ],
    );
  }
}

class _SideAction extends StatelessWidget {
  const _SideAction({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Icon(icon, color: Colors.white, size: 28),
        const SizedBox(height: 4),
        Text(label, style: const TextStyle(color: Colors.white, fontSize: 12)),
      ],
    );
  }
}
