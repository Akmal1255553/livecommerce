class ProductCard {
  const ProductCard({
    required this.id,
    required this.title,
    required this.price,
    this.compareAtPrice,
    this.discountPercent,
    this.currency = 'UZS',
    this.thumbnail,
    this.storeName,
    required this.status,
    this.isPurchasable = false,
  });

  final String id;
  final String title;
  final double price;
  final double? compareAtPrice;
  final int? discountPercent;
  final String currency;
  final String? thumbnail;
  final String? storeName;
  final String status;
  final bool isPurchasable;

  factory ProductCard.fromJson(Map<String, dynamic> json) {
    return ProductCard(
      id: json['id'] as String,
      title: json['title'] as String,
      price: (json['price'] as num).toDouble(),
      compareAtPrice: json['compare_at_price'] != null
          ? (json['compare_at_price'] as num).toDouble()
          : null,
      discountPercent: json['discount_percent'] as int?,
      currency: json['currency'] as String? ?? 'UZS',
      thumbnail: json['thumbnail'] as String?,
      storeName: json['store_name'] as String?,
      status: json['status'] as String,
      isPurchasable: json['is_purchasable'] as bool? ?? false,
    );
  }
}

class VideoProductTag {
  const VideoProductTag({
    required this.product,
    required this.sortOrder,
    this.isFeatured = false,
    this.startsAt,
    this.endsAt,
    this.positionX,
    this.positionY,
    this.productVersion = 1,
  });

  final ProductCard product;
  final int sortOrder;
  final bool isFeatured;
  final double? startsAt;
  final double? endsAt;
  final double? positionX;
  final double? positionY;
  final int productVersion;

  factory VideoProductTag.fromJson(Map<String, dynamic> json) {
    return VideoProductTag(
      product: ProductCard.fromJson(json['product'] as Map<String, dynamic>),
      sortOrder: json['sort_order'] as int? ?? 0,
      isFeatured: json['is_featured'] as bool? ?? false,
      startsAt: json['starts_at'] != null ? (json['starts_at'] as num).toDouble() : null,
      endsAt: json['ends_at'] != null ? (json['ends_at'] as num).toDouble() : null,
      positionX: json['position_x'] != null ? (json['position_x'] as num).toDouble() : null,
      positionY: json['position_y'] != null ? (json['position_y'] as num).toDouble() : null,
      productVersion: json['product_version'] as int? ?? 1,
    );
  }
}
