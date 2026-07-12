import 'dart:math';

import 'package:dio/dio.dart';
import 'package:livecommerce_mobile/features/commerce/domain/entities/order.dart';
import 'package:livecommerce_mobile/features/seller/domain/entities/seller_models.dart';

class SellerRemoteDataSource {
  SellerRemoteDataSource(this._dio);

  final Dio _dio;

  Future<SellerStore> apply({
    required String storeName,
    String? description,
  }) async {
    final response = await _dio.post<Map<String, dynamic>>(
      '/seller/apply',
      data: {
        'store_name': storeName,
        if (description != null && description.isNotEmpty) 'description': description,
      },
    );
    return SellerStore.fromJson(response.data!['data'] as Map<String, dynamic>);
  }

  Future<SellerDashboard> fetchDashboard() async {
    final response = await _dio.get<Map<String, dynamic>>('/seller/dashboard');
    return SellerDashboard.fromJson(response.data!['data'] as Map<String, dynamic>);
  }

  Future<List<SellerCategory>> fetchCategories() async {
    final response = await _dio.get<Map<String, dynamic>>('/categories');
    final data = response.data!['data'] as List<dynamic>;
    return data
        .map((item) => SellerCategory.fromJson(item as Map<String, dynamic>))
        .toList();
  }

  Future<List<SellerProductSummary>> fetchMyProducts({int page = 1}) async {
    final response = await _dio.get<Map<String, dynamic>>(
      '/products',
      queryParameters: {'mine': 1, 'page': page},
    );
    final data = response.data!['data'] as List<dynamic>;
    return data
        .map((item) => SellerProductSummary.fromJson(item as Map<String, dynamic>))
        .toList();
  }

  Future<SellerProductSummary> createProduct({
    required String title,
    String? description,
    required int categoryId,
    required double price,
    double? compareAtPrice,
    required int stockQuantity,
    String? sku,
    String? imageUrl,
  }) async {
    final response = await _dio.post<Map<String, dynamic>>(
      '/products',
      data: {
        'title': title,
        if (description != null) 'description': description,
        'category_id': categoryId,
        'price': price,
        if (compareAtPrice != null) 'compare_at_price': compareAtPrice,
        'stock_quantity': stockQuantity,
        if (sku != null && sku.isNotEmpty) 'sku': sku,
        'status': 'active',
        if (imageUrl != null && imageUrl.isNotEmpty) 'images': [imageUrl],
      },
    );
    return SellerProductSummary.fromJson(response.data!['data'] as Map<String, dynamic>);
  }

  Future<void> deleteProduct(String id) async {
    await _dio.delete<void>('/products/$id');
  }

  Future<List<SellerOrderSummary>> fetchSellerOrders({int page = 1}) async {
    final response = await _dio.get<Map<String, dynamic>>(
      '/seller/orders',
      queryParameters: {'page': page},
    );
    final data = response.data!['data'] as List<dynamic>;
    return data
        .map((item) => SellerOrderSummary.fromJson(item as Map<String, dynamic>))
        .toList();
  }

  Future<Order> fetchSellerOrder(String id) async {
    final response = await _dio.get<Map<String, dynamic>>('/seller/orders/$id');
    return Order.fromJson(response.data!['data'] as Map<String, dynamic>);
  }

  Future<Order> updateOrderStatus({
    required String orderId,
    required String status,
    int? version,
    String? trackingNumber,
    String? carrier,
  }) async {
    final response = await _dio.put<Map<String, dynamic>>(
      '/seller/orders/$orderId/status',
      data: {
        'status': status,
        if (trackingNumber != null) 'tracking_number': trackingNumber,
        if (carrier != null) 'carrier': carrier,
      },
      options: Options(
        headers: {
          'Idempotency-Key': _newIdempotencyKey(),
          if (version != null) 'If-Match': version.toString(),
        },
      ),
    );
    return Order.fromJson(response.data!['data'] as Map<String, dynamic>);
  }

  Future<SellerStore> fetchStoreBySlug(String slug) async {
    final response = await _dio.get<Map<String, dynamic>>('/stores/$slug');
    return SellerStore.fromJson(response.data!['data'] as Map<String, dynamic>);
  }

  Future<List<SellerProductSummary>> fetchStoreProducts(String slug, {int page = 1}) async {
    final response = await _dio.get<Map<String, dynamic>>(
      '/stores/$slug/products',
      queryParameters: {'page': page},
    );
    final data = response.data!['data'] as List<dynamic>;
    return data
        .map((item) => SellerProductSummary.fromJson(item as Map<String, dynamic>))
        .toList();
  }

  static String _newIdempotencyKey() {
    const chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    final random = Random.secure();
    return List.generate(32, (_) => chars[random.nextInt(chars.length)]).join();
  }
}

class SellerRepository {
  SellerRepository({required SellerRemoteDataSource remote}) : _remote = remote;

  final SellerRemoteDataSource _remote;

  Future<SellerStore> apply({required String storeName, String? description}) =>
      _remote.apply(storeName: storeName, description: description);

  Future<SellerDashboard> dashboard() => _remote.fetchDashboard();

  Future<List<SellerCategory>> categories() => _remote.fetchCategories();

  Future<List<SellerProductSummary>> myProducts({int page = 1}) =>
      _remote.fetchMyProducts(page: page);

  Future<SellerProductSummary> createProduct({
    required String title,
    String? description,
    required int categoryId,
    required double price,
    double? compareAtPrice,
    required int stockQuantity,
    String? sku,
    String? imageUrl,
  }) =>
      _remote.createProduct(
        title: title,
        description: description,
        categoryId: categoryId,
        price: price,
        compareAtPrice: compareAtPrice,
        stockQuantity: stockQuantity,
        sku: sku,
        imageUrl: imageUrl,
      );

  Future<void> deleteProduct(String id) => _remote.deleteProduct(id);

  Future<List<SellerOrderSummary>> sellerOrders({int page = 1}) =>
      _remote.fetchSellerOrders(page: page);

  Future<Order> sellerOrder(String id) => _remote.fetchSellerOrder(id);

  Future<Order> updateOrderStatus({
    required String orderId,
    required String status,
    int? version,
    String? trackingNumber,
    String? carrier,
  }) =>
      _remote.updateOrderStatus(
        orderId: orderId,
        status: status,
        version: version,
        trackingNumber: trackingNumber,
        carrier: carrier,
      );

  Future<SellerStore> storeBySlug(String slug) => _remote.fetchStoreBySlug(slug);

  Future<List<SellerProductSummary>> storeProducts(String slug, {int page = 1}) =>
      _remote.fetchStoreProducts(slug, page: page);
}
