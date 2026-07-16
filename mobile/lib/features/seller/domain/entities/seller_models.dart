class SellerStore {
  const SellerStore({
    required this.id,
    required this.name,
    required this.slug,
    this.logoUrl,
    this.description,
    required this.status,
  });

  final String id;
  final String name;
  final String slug;
  final String? logoUrl;
  final String? description;
  final String status;

  factory SellerStore.fromJson(Map<String, dynamic> json) {
    return SellerStore(
      id: json['id'] as String,
      name: json['name'] as String,
      slug: json['slug'] as String,
      logoUrl: json['logo_url'] as String?,
      description: json['description'] as String?,
      status: json['status'] as String? ?? 'active',
    );
  }
}

class SellerDashboard {
  const SellerDashboard({
    required this.store,
    required this.totalProducts,
    required this.activeProducts,
    required this.lowStockProducts,
    required this.pendingOrders,
    required this.totalOrders,
    required this.totalRevenue,
    this.currency = 'UZS',
  });

  final SellerStore store;
  final int totalProducts;
  final int activeProducts;
  final int lowStockProducts;
  final int pendingOrders;
  final int totalOrders;
  final int totalRevenue;
  final String currency;

  factory SellerDashboard.fromJson(Map<String, dynamic> json) {
    return SellerDashboard(
      store: SellerStore.fromJson(json['store'] as Map<String, dynamic>),
      totalProducts: json['total_products'] as int? ?? 0,
      activeProducts: json['active_products'] as int? ?? 0,
      lowStockProducts: json['low_stock_products'] as int? ?? 0,
      pendingOrders: json['pending_orders'] as int? ?? 0,
      totalOrders: json['total_orders'] as int? ?? 0,
      totalRevenue: json['total_revenue'] as int? ?? 0,
      currency: json['currency'] as String? ?? 'UZS',
    );
  }
}

class SellerCategory {
  const SellerCategory({required this.id, required this.name});

  final int id;
  final String name;

  factory SellerCategory.fromJson(Map<String, dynamic> json) {
    return SellerCategory(
      id: json['id'] as int,
      name: json['name'] as String,
    );
  }
}

class SellerProductSummary {
  const SellerProductSummary({
    required this.id,
    required this.title,
    required this.price,
    required this.stockQuantity,
    required this.status,
    this.thumbnail,
  });

  final String id;
  final String title;
  final double price;
  final int stockQuantity;
  final String status;
  final String? thumbnail;

  factory SellerProductSummary.fromJson(Map<String, dynamic> json) {
    final images = json['images'] as List<dynamic>? ?? [];
    String? thumb;
    if (images.isNotEmpty) {
      final first = images.first as Map<String, dynamic>;
      thumb = first['url'] as String?;
    }
    thumb ??= json['thumbnail'] as String?;

    return SellerProductSummary(
      id: json['id'] as String,
      title: json['title'] as String,
      price: (json['price'] as num).toDouble(),
      stockQuantity: json['stock_quantity'] as int? ?? 0,
      status: json['status'] as String,
      thumbnail: thumb,
    );
  }
}

class SellerOrderSummary {
  const SellerOrderSummary({
    required this.id,
    required this.orderNumber,
    required this.status,
    required this.totalAmount,
    this.currency = 'UZS',
    this.createdAt,
  });

  final String id;
  final String orderNumber;
  final String status;
  final int totalAmount;
  final String currency;
  final String? createdAt;

  factory SellerOrderSummary.fromJson(Map<String, dynamic> json) {
    final totals = json['totals'] as Map<String, dynamic>? ?? {};
    final total = totals['total'] as Map<String, dynamic>? ?? {};

    return SellerOrderSummary(
      id: json['id'] as String,
      orderNumber: json['order_number'] as String,
      status: json['status'] as String,
      totalAmount: total['amount'] as int? ?? 0,
      currency: totals['currency'] as String? ?? 'UZS',
      createdAt: json['created_at'] as String?,
    );
  }
}

class LiveAnalyticsTopProduct {
  const LiveAnalyticsTopProduct({
    required this.productId,
    this.title,
    this.pins = 0,
    this.addToCart = 0,
  });

  final String productId;
  final String? title;
  final int pins;
  final int addToCart;

  factory LiveAnalyticsTopProduct.fromJson(Map<String, dynamic> json) {
    return LiveAnalyticsTopProduct(
      productId: json['product_id'] as String,
      title: json['title'] as String?,
      pins: json['pins'] as int? ?? 0,
      addToCart: json['add_to_cart'] as int? ?? 0,
    );
  }
}

class LiveSessionAnalytics {
  const LiveSessionAnalytics({
    required this.sessionId,
    required this.title,
    required this.status,
    this.startedAt,
    this.endedAt,
    this.durationSeconds,
    this.peakViewers = 0,
    this.uniqueViewers = 0,
    this.currentViewers = 0,
    this.chatMessages = 0,
    this.productsPinned = 0,
    this.productsAddedToCart = 0,
    this.cartConversionRate = 0,
    this.topProducts = const [],
    this.replayUrl,
  });

  final String sessionId;
  final String title;
  final String status;
  final String? startedAt;
  final String? endedAt;
  final int? durationSeconds;
  final int peakViewers;
  final int uniqueViewers;
  final int currentViewers;
  final int chatMessages;
  final int productsPinned;
  final int productsAddedToCart;
  final double cartConversionRate;
  final List<LiveAnalyticsTopProduct> topProducts;
  final String? replayUrl;

  factory LiveSessionAnalytics.fromJson(Map<String, dynamic> json) {
    final top = json['top_products'] as List<dynamic>? ?? [];
    return LiveSessionAnalytics(
      sessionId: json['session_id'] as String,
      title: json['title'] as String? ?? 'Live',
      status: json['status'] as String? ?? 'ended',
      startedAt: json['started_at'] as String?,
      endedAt: json['ended_at'] as String?,
      durationSeconds: json['duration_seconds'] as int?,
      peakViewers: json['peak_viewers'] as int? ?? 0,
      uniqueViewers: json['unique_viewers'] as int? ?? 0,
      currentViewers: json['current_viewers'] as int? ?? 0,
      chatMessages: json['chat_messages'] as int? ?? 0,
      productsPinned: json['products_pinned'] as int? ?? 0,
      productsAddedToCart: json['products_added_to_cart'] as int? ?? 0,
      cartConversionRate: (json['cart_conversion_rate'] as num?)?.toDouble() ?? 0,
      topProducts: top
          .map((e) => LiveAnalyticsTopProduct.fromJson(e as Map<String, dynamic>))
          .toList(),
      replayUrl: json['replay_url'] as String?,
    );
  }
}

class SellerLiveAnalyticsOverview {
  const SellerLiveAnalyticsOverview({
    this.totalSessions = 0,
    this.totalUniqueViewers = 0,
    this.totalPeakViewers = 0,
    this.totalAddToCart = 0,
    this.totalPins = 0,
    this.avgCartConversionRate = 0,
    this.sessions = const [],
  });

  final int totalSessions;
  final int totalUniqueViewers;
  final int totalPeakViewers;
  final int totalAddToCart;
  final int totalPins;
  final double avgCartConversionRate;
  final List<LiveSessionAnalytics> sessions;

  factory SellerLiveAnalyticsOverview.fromJson(Map<String, dynamic> json) {
    final sessionsJson = json['sessions'] as List<dynamic>? ?? [];
    return SellerLiveAnalyticsOverview(
      totalSessions: json['total_sessions'] as int? ?? 0,
      totalUniqueViewers: json['total_unique_viewers'] as int? ?? 0,
      totalPeakViewers: json['total_peak_viewers'] as int? ?? 0,
      totalAddToCart: json['total_add_to_cart'] as int? ?? 0,
      totalPins: json['total_pins'] as int? ?? 0,
      avgCartConversionRate:
          (json['avg_cart_conversion_rate'] as num?)?.toDouble() ?? 0,
      sessions: sessionsJson
          .map((e) => LiveSessionAnalytics.fromJson(e as Map<String, dynamic>))
          .toList(),
    );
  }
}
