import 'dart:math';

import 'package:dio/dio.dart';
import 'package:livecommerce_mobile/core/network/api_json.dart';
import 'package:livecommerce_mobile/features/commerce/domain/entities/cart.dart';
import 'package:livecommerce_mobile/features/commerce/domain/entities/order.dart';
import 'package:livecommerce_mobile/features/commerce/domain/entities/product_detail.dart';

class CommerceRemoteDataSource {
  CommerceRemoteDataSource(this._dio);

  final Dio _dio;

  Future<ProductDetail> fetchProduct(String id) async {
    final response = await _dio.get<Map<String, dynamic>>('/products/$id');
    final data = response.data!['data'] as Map<String, dynamic>;
    return ProductDetail.fromJson(data);
  }

  Future<Cart> fetchCart() async {
    final response = await _dio.get<Map<String, dynamic>>('/cart');
    final data = response.data!['data'] as Map<String, dynamic>;
    return Cart.fromJson(data);
  }

  Future<Cart> addToCart({
    required String productId,
    String? variantId,
    int quantity = 1,
  }) async {
    final response = await _dio.post<Map<String, dynamic>>(
      '/cart/items',
      data: {
        'product_id': productId,
        if (variantId != null) 'variant_id': variantId,
        'quantity': quantity,
      },
    );
    final data = response.data!['data'] as Map<String, dynamic>;
    return Cart.fromJson(data);
  }

  Future<Cart> updateCartItem({
    required String itemId,
    required int quantity,
  }) async {
    final response = await _dio.put<Map<String, dynamic>>(
      '/cart/items/$itemId',
      data: {'quantity': quantity},
    );
    final data = response.data!['data'] as Map<String, dynamic>;
    return Cart.fromJson(data);
  }

  Future<Cart> removeCartItem(String itemId) async {
    final response = await _dio.delete<Map<String, dynamic>>('/cart/items/$itemId');
    final data = response.data!['data'] as Map<String, dynamic>;
    return Cart.fromJson(data);
  }

  Future<CheckoutResult> checkout({
    required int cartVersion,
    required ShippingAddress shippingAddress,
    String paymentMethod = 'click',
    String? couponCode,
    String? notes,
  }) async {
    final response = await _dio.post<Map<String, dynamic>>(
      '/checkout',
      data: {
        'cart_version': cartVersion,
        'payment_method': paymentMethod,
        'shipping_address': shippingAddress.toJson(),
        if (couponCode != null) 'coupon_code': couponCode,
        if (notes != null) 'notes': notes,
      },
      options: Options(
        headers: {'Idempotency-Key': _newIdempotencyKey()},
      ),
    );

    final data = response.data!['data'] as Map<String, dynamic>;
    return CheckoutResult(
      order: Order.fromJson(unwrapApiResource(data['order'])),
      paymentUrl: data['payment_url'] as String?,
    );
  }

  Future<OrderPage> fetchOrders({int page = 1}) async {
    final response = await _dio.get<Map<String, dynamic>>(
      '/orders',
      queryParameters: {'page': page},
    );

    final data = response.data!['data'] as List<dynamic>;
    final meta = response.data!['meta'] as Map<String, dynamic>? ?? {};

    return OrderPage(
      orders: data
          .map((item) => Order.fromJson(item as Map<String, dynamic>))
          .toList(),
      currentPage: meta['currentPage'] as int? ?? page,
      lastPage: meta['lastPage'] as int? ?? 1,
      total: meta['total'] as int? ?? data.length,
    );
  }

  Future<Order> fetchOrder(String id) async {
    final response = await _dio.get<Map<String, dynamic>>('/orders/$id');
    final data = response.data!['data'] as Map<String, dynamic>;
    return Order.fromJson(data);
  }

  Future<Order> completeSandboxPayment({
    required String orderId,
    required String result,
  }) async {
    final response = await _dio.post<Map<String, dynamic>>(
      '/payments/sandbox/$orderId/complete',
      data: {'result': result},
    );
    final data = response.data!['data'] as Map<String, dynamic>;
    return Order.fromJson(unwrapApiResource(data['order']));
  }

  Future<Order> requestRefund({
    required String orderId,
    required String reason,
  }) async {
    final response = await _dio.post<Map<String, dynamic>>(
      '/orders/$orderId/refund',
      data: {'reason': reason},
      options: Options(
        headers: {'Idempotency-Key': _newIdempotencyKey()},
      ),
    );
    final data = response.data!['data'] as Map<String, dynamic>;
    return Order.fromJson(data);
  }

  static String _newIdempotencyKey() {
    const chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    final random = Random.secure();
    return List.generate(32, (_) => chars[random.nextInt(chars.length)]).join();
  }
}

class CommerceRepository {
  CommerceRepository({required CommerceRemoteDataSource remote}) : _remote = remote;

  final CommerceRemoteDataSource _remote;

  Future<ProductDetail> getProduct(String id) => _remote.fetchProduct(id);

  Future<Cart> getCart() => _remote.fetchCart();

  Future<Cart> addToCart({
    required String productId,
    String? variantId,
    int quantity = 1,
  }) =>
      _remote.addToCart(productId: productId, variantId: variantId, quantity: quantity);

  Future<Cart> updateCartItem({required String itemId, required int quantity}) =>
      _remote.updateCartItem(itemId: itemId, quantity: quantity);

  Future<Cart> removeCartItem(String itemId) => _remote.removeCartItem(itemId);

  Future<CheckoutResult> checkout({
    required int cartVersion,
    required ShippingAddress shippingAddress,
    String paymentMethod = 'click',
    String? couponCode,
    String? notes,
  }) =>
      _remote.checkout(
        cartVersion: cartVersion,
        shippingAddress: shippingAddress,
        paymentMethod: paymentMethod,
        couponCode: couponCode,
        notes: notes,
      );

  Future<OrderPage> listOrders({int page = 1}) => _remote.fetchOrders(page: page);

  Future<Order> getOrder(String id) => _remote.fetchOrder(id);

  Future<Order> completeSandboxPayment({
    required String orderId,
    required String result,
  }) =>
      _remote.completeSandboxPayment(orderId: orderId, result: result);

  Future<Order> requestRefund({
    required String orderId,
    required String reason,
  }) =>
      _remote.requestRefund(orderId: orderId, reason: reason);
}
