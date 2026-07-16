import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:livecommerce_mobile/features/commerce/presentation/providers/commerce_providers.dart';
import 'package:livecommerce_mobile/features/live/domain/entities/live_session.dart';
import 'package:livecommerce_mobile/features/live/presentation/providers/live_providers.dart';
import 'package:livecommerce_mobile/features/seller/presentation/providers/seller_providers.dart';

class LiveRoomScreen extends ConsumerStatefulWidget {
  const LiveRoomScreen({
    super.key,
    required this.sessionId,
    this.asHost = false,
  });

  final String sessionId;
  final bool asHost;

  @override
  ConsumerState<LiveRoomScreen> createState() => _LiveRoomScreenState();
}

class _LiveRoomScreenState extends ConsumerState<LiveRoomScreen> {
  final _chatController = TextEditingController();

  @override
  void initState() {
    super.initState();
    Future.microtask(
      () => ref
          .read(liveRoomProvider(widget.sessionId).notifier)
          .enter(asHost: widget.asHost),
    );
  }

  @override
  void dispose() {
    _chatController.dispose();
    super.dispose();
  }

  Future<void> _send() async {
    final ok = await ref
        .read(liveRoomProvider(widget.sessionId).notifier)
        .sendChat(_chatController.text);
    if (ok) {
      _chatController.clear();
    }
  }

  Future<void> _pickPin() async {
    await ref.read(sellerProductsProvider.notifier).load();
    if (!mounted) {
      return;
    }
    final products = ref.read(sellerProductsProvider).products;
    if (products.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('No products to pin')),
      );
      return;
    }

    final productId = await showModalBottomSheet<String>(
      context: context,
      builder: (context) => ListView(
        shrinkWrap: true,
        children: [
          const ListTile(title: Text('Pin a product')),
          ...products.map(
            (p) => ListTile(
              title: Text(p.title),
              subtitle: Text('${p.price.toInt()} UZS'),
              onTap: () => Navigator.pop(context, p.id),
            ),
          ),
        ],
      ),
    );

    if (productId == null) {
      return;
    }
    final ok = await ref
        .read(liveRoomProvider(widget.sessionId).notifier)
        .pinProduct(productId);
    if (!mounted) {
      return;
    }
    if (!ok) {
      final error = ref.read(liveRoomProvider(widget.sessionId)).error;
      if (error != null) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error)));
      }
    }
  }

  Future<void> _addPinnedToCart(String productId) async {
    final ok = await ref
        .read(liveRoomProvider(widget.sessionId).notifier)
        .addPinnedToCart(productId);
    if (!mounted) {
      return;
    }
    if (ok) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Added to cart')),
      );
      return;
    }
    final error = ref.read(liveRoomProvider(widget.sessionId)).error;
    if (error != null) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error)));
    }
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(liveRoomProvider(widget.sessionId));
    final cartCount = ref.watch(cartNotifierProvider).itemCount;
    final session = state.session;

    return Scaffold(
      backgroundColor: Colors.black,
      body: state.isLoading && session == null
          ? const Center(child: CircularProgressIndicator())
          : state.error != null && session == null
              ? Center(
                  child: Text(state.error!, style: const TextStyle(color: Colors.white)),
                )
              : SafeArea(
                  child: Column(
                    children: [
                      _LiveHeader(
                        session: session,
                        isHost: state.isHost,
                        cartCount: cartCount,
                        onCart: () => context.push('/cart'),
                        onEnd: () async {
                          final ok = await ref
                              .read(liveRoomProvider(widget.sessionId).notifier)
                              .endLive();
                          if (ok && context.mounted) {
                            context.pop();
                          }
                        },
                        onClose: () => context.pop(),
                        onPin: state.isHost ? _pickPin : null,
                      ),
                      Expanded(
                        child: Stack(
                          fit: StackFit.expand,
                          children: [
                            const _LivePlaceholder(),
                            Positioned(
                              left: 12,
                              right: 12,
                              bottom: 72,
                              height: 110,
                              child: _ChatList(messages: state.messages),
                            ),
                            if (session != null && session.pinnedProducts.isNotEmpty)
                              Positioned(
                                left: 12,
                                right: 12,
                                bottom: 120,
                                child: SizedBox(
                                  height: 72,
                                  child: ListView.separated(
                                    scrollDirection: Axis.horizontal,
                                    itemCount: session.pinnedProducts.length,
                                    separatorBuilder: (_, __) =>
                                        const SizedBox(width: 8),
                                    itemBuilder: (context, index) {
                                      final product =
                                          session.pinnedProducts[index];
                                      return _PinnedChip(
                                        product: product,
                                        isAdding: state.isAddingToCart,
                                        onAdd: state.isHost
                                            ? null
                                            : () => _addPinnedToCart(
                                                  product.productId,
                                                ),
                                        onView: () => context.push(
                                          '/products/${product.productId}',
                                        ),
                                      );
                                    },
                                  ),
                                ),
                              ),
                          ],
                        ),
                      ),
                      Padding(
                        padding: const EdgeInsets.fromLTRB(12, 8, 12, 12),
                        child: Row(
                          children: [
                            Expanded(
                              child: TextField(
                                controller: _chatController,
                                style: const TextStyle(color: Colors.white),
                                decoration: InputDecoration(
                                  hintText: 'Say something…',
                                  hintStyle:
                                      const TextStyle(color: Colors.white54),
                                  filled: true,
                                  fillColor: Colors.white12,
                                  border: OutlineInputBorder(
                                    borderRadius: BorderRadius.circular(24),
                                    borderSide: BorderSide.none,
                                  ),
                                  contentPadding: const EdgeInsets.symmetric(
                                    horizontal: 16,
                                    vertical: 10,
                                  ),
                                ),
                                onSubmitted: (_) => _send(),
                              ),
                            ),
                            const SizedBox(width: 8),
                            IconButton.filled(
                              onPressed: state.isSending ? null : _send,
                              icon: const Icon(Icons.send),
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

class _LiveHeader extends StatelessWidget {
  const _LiveHeader({
    required this.session,
    required this.isHost,
    required this.onClose,
    required this.onEnd,
    required this.onCart,
    this.cartCount = 0,
    this.onPin,
  });

  final LiveSession? session;
  final bool isHost;
  final VoidCallback onClose;
  final VoidCallback onEnd;
  final VoidCallback onCart;
  final int cartCount;
  final VoidCallback? onPin;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      child: Row(
        children: [
          IconButton(
            onPressed: onClose,
            icon: const Icon(Icons.close, color: Colors.white),
          ),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
            decoration: BoxDecoration(
              color: Colors.red,
              borderRadius: BorderRadius.circular(4),
            ),
            child: const Text(
              'LIVE',
              style: TextStyle(
                color: Colors.white,
                fontWeight: FontWeight.w800,
                fontSize: 12,
              ),
            ),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              session?.title ?? 'Live',
              style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w600),
              overflow: TextOverflow.ellipsis,
            ),
          ),
          Text(
            '${session?.viewerCount ?? 0}',
            style: const TextStyle(color: Colors.white70),
          ),
          const SizedBox(width: 4),
          const Icon(Icons.remove_red_eye_outlined, color: Colors.white70, size: 18),
          if (!isHost)
            Stack(
              clipBehavior: Clip.none,
              children: [
                IconButton(
                  onPressed: onCart,
                  icon: const Icon(Icons.shopping_cart_outlined, color: Colors.white),
                ),
                if (cartCount > 0)
                  Positioned(
                    right: 6,
                    top: 6,
                    child: Container(
                      padding: const EdgeInsets.all(4),
                      decoration: const BoxDecoration(
                        color: Color(0xFFFF6B6B),
                        shape: BoxShape.circle,
                      ),
                      constraints: const BoxConstraints(minWidth: 16, minHeight: 16),
                      child: Text(
                        '$cartCount',
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
          if (isHost && onPin != null)
            IconButton(
              onPressed: onPin,
              icon: const Icon(Icons.push_pin_outlined, color: Colors.white),
            ),
          if (isHost)
            TextButton(
              onPressed: onEnd,
              child: const Text('End', style: TextStyle(color: Colors.redAccent)),
            ),
        ],
      ),
    );
  }
}

class _LivePlaceholder extends StatelessWidget {
  const _LivePlaceholder();

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [Color(0xFF1A1A2E), Color(0xFF16213E), Color(0xFF0F3460)],
        ),
      ),
      child: const Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.videocam, color: Colors.white54, size: 64),
            SizedBox(height: 12),
            Text(
              'Live placeholder',
              style: TextStyle(color: Colors.white70, fontSize: 16),
            ),
            SizedBox(height: 4),
            Text(
              'Agora camera comes in a later pass',
              style: TextStyle(color: Colors.white38, fontSize: 12),
            ),
          ],
        ),
      ),
    );
  }
}

class _PinnedChip extends StatelessWidget {
  const _PinnedChip({
    required this.product,
    this.onAdd,
    this.onView,
    this.isAdding = false,
  });

  final LivePinnedProduct product;
  final VoidCallback? onAdd;
  final VoidCallback? onView;
  final bool isAdding;

  @override
  Widget build(BuildContext context) {
    final price = NumberFormat('#,###').format(product.price.toInt());
    return Material(
      color: Colors.black54,
      borderRadius: BorderRadius.circular(12),
      child: Padding(
        padding: const EdgeInsets.all(8),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            InkWell(
              borderRadius: BorderRadius.circular(8),
              onTap: onView,
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  if (product.thumbnail != null)
                    ClipRRect(
                      borderRadius: BorderRadius.circular(8),
                      child: Image.network(
                        product.thumbnail!,
                        width: 48,
                        height: 48,
                        fit: BoxFit.cover,
                        errorBuilder: (_, __, ___) =>
                            const Icon(Icons.shopping_bag, color: Colors.white70),
                      ),
                    )
                  else
                    const Icon(Icons.shopping_bag, color: Colors.white70),
                  const SizedBox(width: 8),
                  ConstrainedBox(
                    constraints: const BoxConstraints(maxWidth: 120),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Text(
                          product.title,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style:
                              const TextStyle(color: Colors.white, fontSize: 12),
                        ),
                        Text(
                          '$price ${product.currency}',
                          style: const TextStyle(
                            color: Color(0xFFFF6B6B),
                            fontWeight: FontWeight.w700,
                            fontSize: 13,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            if (onAdd != null) ...[
              const SizedBox(width: 6),
              SizedBox(
                width: 36,
                height: 36,
                child: IconButton.filled(
                  style: IconButton.styleFrom(
                    backgroundColor: const Color(0xFFFF6B6B),
                    padding: EdgeInsets.zero,
                  ),
                  onPressed: isAdding ? null : onAdd,
                  icon: isAdding
                      ? const SizedBox(
                          width: 16,
                          height: 16,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.white,
                          ),
                        )
                      : const Icon(Icons.add, size: 20),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _ChatList extends StatelessWidget {
  const _ChatList({required this.messages});

  final List<LiveChatMessage> messages;

  @override
  Widget build(BuildContext context) {
    if (messages.isEmpty) {
      return const SizedBox.shrink();
    }

    return ListView.builder(
      reverse: true,
      itemCount: messages.length,
      itemBuilder: (context, index) {
        final message = messages[messages.length - 1 - index];
        final prefix = message.type == 'system'
            ? '🔔 '
            : message.type == 'commerce'
                ? '🛒 '
                : '@${message.username ?? 'user'}: ';
        return Padding(
          padding: const EdgeInsets.only(bottom: 4),
          child: Text(
            '$prefix${message.message}',
            style: TextStyle(
              color: message.type == 'system' ? Colors.amberAccent : Colors.white,
              fontSize: 13,
              shadows: const [Shadow(blurRadius: 4, color: Colors.black)],
            ),
          ),
        );
      },
    );
  }
}
