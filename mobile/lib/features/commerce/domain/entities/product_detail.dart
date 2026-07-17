import 'package:livecommerce_mobile/features/feed/domain/entities/product_card.dart';

class ProductImage {
  const ProductImage({
    required this.id,
    required this.url,
    this.sortOrder = 0,
  });

  final String id;
  final String url;
  final int sortOrder;

  factory ProductImage.fromJson(Map<String, dynamic> json) {
    return ProductImage(
      id: json['id'] as String,
      url: json['url'] as String,
      sortOrder: json['sort_order'] as int? ?? 0,
    );
  }
}

class ProductVariant {
  const ProductVariant({
    required this.id,
    required this.name,
    required this.value,
    this.sku,
    this.priceAdjustment = 0,
    this.stockQuantity = 0,
  });

  final String id;
  final String name;
  final String value;
  final String? sku;
  final double priceAdjustment;
  final int stockQuantity;

  factory ProductVariant.fromJson(Map<String, dynamic> json) {
    return ProductVariant(
      id: json['id'] as String,
      name: json['name'] as String,
      value: json['value'] as String,
      sku: json['sku'] as String?,
      priceAdjustment: (json['price_adjustment'] as num?)?.toDouble() ?? 0,
      stockQuantity: json['stock_quantity'] as int? ?? 0,
    );
  }
}

class ProductDetail {
  const ProductDetail({
    required this.id,
    required this.title,
    this.description,
    required this.price,
    this.compareAtPrice,
    this.discountPercent,
    this.currency = 'UZS',
    this.sku,
    this.stockQuantity = 0,
    required this.status,
    this.ratingAvg = 0,
    this.reviewCount = 0,
    this.images = const [],
    this.variants = const [],
    this.storeName,
    this.sellerUserId,
    this.isPurchasable = false,
  });

  final String id;
  final String title;
  final String? description;
  final double price;
  final double? compareAtPrice;
  final int? discountPercent;
  final String currency;
  final String? sku;
  final int stockQuantity;
  final String status;
  final double ratingAvg;
  final int reviewCount;
  final List<ProductImage> images;
  final List<ProductVariant> variants;
  final String? storeName;
  final String? sellerUserId;
  final bool isPurchasable;

  String? get primaryImageUrl =>
      images.isNotEmpty ? images.first.url : null;

  ProductCard toCard() {
    return ProductCard(
      id: id,
      title: title,
      price: price,
      compareAtPrice: compareAtPrice,
      discountPercent: discountPercent,
      currency: currency,
      thumbnail: primaryImageUrl,
      storeName: storeName,
      status: status,
      isPurchasable: isPurchasable,
    );
  }

  factory ProductDetail.fromJson(Map<String, dynamic> json) {
    final imagesJson = json['images'] as List<dynamic>? ?? [];
    final variantsJson = json['variants'] as List<dynamic>? ?? [];
    final store = json['store'] as Map<String, dynamic>?;

    return ProductDetail(
      id: json['id'] as String,
      title: json['title'] as String,
      description: json['description'] as String?,
      price: (json['price'] as num).toDouble(),
      compareAtPrice: json['compare_at_price'] != null
          ? (json['compare_at_price'] as num).toDouble()
          : null,
      discountPercent: json['discount_percent'] as int?,
      currency: json['currency'] as String? ?? 'UZS',
      sku: json['sku'] as String?,
      stockQuantity: json['stock_quantity'] as int? ?? 0,
      status: json['status'] as String,
      ratingAvg: (json['rating_avg'] as num?)?.toDouble() ?? 0,
      reviewCount: json['review_count'] as int? ?? 0,
      images: imagesJson
          .map((item) => ProductImage.fromJson(item as Map<String, dynamic>))
          .toList(),
      variants: variantsJson
          .map((item) => ProductVariant.fromJson(item as Map<String, dynamic>))
          .toList(),
      storeName: store?['name'] as String?,
      sellerUserId: store?['owner_user_id'] as String?,
      isPurchasable: json['status'] == 'active' && (json['stock_quantity'] as int? ?? 0) > 0,
    );
  }
}
