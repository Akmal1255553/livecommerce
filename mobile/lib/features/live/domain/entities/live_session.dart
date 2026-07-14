class LiveSellerBrief {
  const LiveSellerBrief({
    required this.id,
    required this.username,
    this.displayName,
    this.avatarUrl,
  });

  final String id;
  final String username;
  final String? displayName;
  final String? avatarUrl;

  factory LiveSellerBrief.fromJson(Map<String, dynamic> json) {
    return LiveSellerBrief(
      id: json['id'] as String,
      username: json['username'] as String? ?? '',
      displayName: json['display_name'] as String?,
      avatarUrl: json['avatar_url'] as String?,
    );
  }
}

class LivePinnedProduct {
  const LivePinnedProduct({
    required this.productId,
    required this.title,
    required this.price,
    this.currency = 'UZS',
    this.thumbnail,
    this.offsetSeconds,
  });

  final String productId;
  final String title;
  final double price;
  final String currency;
  final String? thumbnail;
  final int? offsetSeconds;

  factory LivePinnedProduct.fromJson(Map<String, dynamic> json) {
    final product = json['product'] as Map<String, dynamic>? ?? {};
    return LivePinnedProduct(
      productId: json['product_id'] as String? ?? product['id'] as String? ?? '',
      title: product['title'] as String? ?? 'Product',
      price: (product['price'] as num?)?.toDouble() ?? 0,
      currency: product['currency'] as String? ?? 'UZS',
      thumbnail: product['thumbnail'] as String?,
      offsetSeconds: json['offset_seconds'] as int?,
    );
  }
}

class LiveChatMessage {
  const LiveChatMessage({
    required this.id,
    required this.type,
    required this.message,
    this.username,
    this.createdAt,
  });

  final int id;
  final String type;
  final String message;
  final String? username;
  final String? createdAt;

  factory LiveChatMessage.fromJson(Map<String, dynamic> json) {
    final user = json['user'] as Map<String, dynamic>?;
    return LiveChatMessage(
      id: json['id'] as int,
      type: json['type'] as String? ?? 'user',
      message: json['message'] as String? ?? '',
      username: user?['username'] as String? ?? user?['display_name'] as String?,
      createdAt: json['created_at'] as String?,
    );
  }
}

class LiveSession {
  const LiveSession({
    required this.id,
    required this.title,
    required this.status,
    this.channelId,
    this.viewerCount = 0,
    this.peakViewers,
    this.uniqueViewers,
    this.seller,
    this.pinnedProducts = const [],
    this.publisherToken,
    this.subscriberToken,
    this.startedAt,
  });

  final String id;
  final String title;
  final String status;
  final String? channelId;
  final int viewerCount;
  final int? peakViewers;
  final int? uniqueViewers;
  final LiveSellerBrief? seller;
  final List<LivePinnedProduct> pinnedProducts;
  final String? publisherToken;
  final String? subscriberToken;
  final String? startedAt;

  bool get isLive => status == 'live';
  bool get isHostToken => publisherToken != null && publisherToken!.isNotEmpty;

  factory LiveSession.fromJson(Map<String, dynamic> json) {
    final pinnedJson = json['pinned_products'] as List<dynamic>? ?? [];
    final sellerJson = json['seller'];

    return LiveSession(
      id: json['id'] as String,
      title: json['title'] as String? ?? 'Live',
      status: json['status'] as String? ?? 'ended',
      channelId: json['channel_id'] as String?,
      viewerCount: json['viewer_count'] as int? ?? 0,
      peakViewers: json['peak_viewers'] as int?,
      uniqueViewers: json['unique_viewers'] as int?,
      seller: sellerJson is Map<String, dynamic>
          ? LiveSellerBrief.fromJson(sellerJson)
          : null,
      pinnedProducts: pinnedJson
          .map((e) => LivePinnedProduct.fromJson(e as Map<String, dynamic>))
          .toList(),
      publisherToken: json['publisher_token'] as String?,
      subscriberToken: json['subscriber_token'] as String?,
      startedAt: json['started_at'] as String?,
    );
  }
}
