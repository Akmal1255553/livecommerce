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
