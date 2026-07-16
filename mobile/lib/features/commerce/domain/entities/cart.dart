import 'package:livecommerce_mobile/features/commerce/domain/entities/money_amount.dart';
import 'package:livecommerce_mobile/features/feed/domain/entities/product_card.dart';

String? _idToString(Object? value) {
  if (value == null) {
    return null;
  }
  return value.toString();
}

int _toInt(Object? value, {int fallback = 0}) {
  if (value is int) {
    return value;
  }
  if (value is num) {
    return value.toInt();
  }
  return int.tryParse(value?.toString() ?? '') ?? fallback;
}

class CartVariantRef {
  const CartVariantRef({
    required this.id,
    required this.name,
    required this.value,
  });

  final String id;
  final String name;
  final String value;

  factory CartVariantRef.fromJson(Map<String, dynamic> json) {
    return CartVariantRef(
      id: _idToString(json['id']) ?? '',
      name: json['name'] as String? ?? '',
      value: json['value'] as String? ?? '',
    );
  }
}

class CartLineItem {
  const CartLineItem({
    required this.id,
    required this.product,
    this.variant,
    required this.quantity,
    required this.unitPrice,
    required this.lineTotal,
    required this.discountAmount,
  });

  final String id;
  final ProductCard product;
  final CartVariantRef? variant;
  final int quantity;
  final MoneyAmount unitPrice;
  final MoneyAmount lineTotal;
  final MoneyAmount discountAmount;

  factory CartLineItem.fromJson(Map<String, dynamic> json) {
    return CartLineItem(
      id: _idToString(json['id']) ?? '',
      product: ProductCard.fromJson(json['product'] as Map<String, dynamic>),
      variant: json['variant'] != null
          ? CartVariantRef.fromJson(json['variant'] as Map<String, dynamic>)
          : null,
      quantity: _toInt(json['quantity'], fallback: 1),
      unitPrice: MoneyAmount.fromJson(json['unit_price'] as Map<String, dynamic>),
      lineTotal: MoneyAmount.fromJson(json['line_total'] as Map<String, dynamic>),
      discountAmount:
          MoneyAmount.fromJson(json['discount_amount'] as Map<String, dynamic>),
    );
  }
}

class CartSummary {
  const CartSummary({
    required this.subtotal,
    required this.discountTotal,
    required this.shippingEstimate,
    this.currency = 'UZS',
    this.itemCount = 0,
  });

  final MoneyAmount subtotal;
  final MoneyAmount discountTotal;
  final MoneyAmount shippingEstimate;
  final String currency;
  final int itemCount;

  factory CartSummary.fromJson(Map<String, dynamic> json) {
    return CartSummary(
      subtotal: MoneyAmount.fromJson(json['subtotal'] as Map<String, dynamic>),
      discountTotal:
          MoneyAmount.fromJson(json['discount_total'] as Map<String, dynamic>),
      shippingEstimate:
          MoneyAmount.fromJson(json['shipping_estimate'] as Map<String, dynamic>),
      currency: json['currency'] as String? ?? 'UZS',
      itemCount: json['item_count'] as int? ?? 0,
    );
  }
}

class Cart {
  const Cart({
    this.id,
    this.type = 'anonymous',
    this.version = 0,
    this.items = const [],
    required this.summary,
  });

  final String? id;
  final String type;
  final int version;
  final List<CartLineItem> items;
  final CartSummary summary;

  bool get isEmpty => items.isEmpty;

  factory Cart.fromJson(Map<String, dynamic> json) {
    final itemsJson = json['items'] as List<dynamic>? ?? [];

    return Cart(
      id: _idToString(json['id']),
      type: json['type'] as String? ?? 'anonymous',
      version: _toInt(json['version']),
      items: itemsJson
          .map((item) => CartLineItem.fromJson(item as Map<String, dynamic>))
          .toList(),
      summary: CartSummary.fromJson(json['summary'] as Map<String, dynamic>),
    );
  }
}
