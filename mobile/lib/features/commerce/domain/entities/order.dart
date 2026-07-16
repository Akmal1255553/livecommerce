import 'package:livecommerce_mobile/features/commerce/domain/entities/money_amount.dart';

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

class ShippingAddress {
  const ShippingAddress({
    required this.fullName,
    required this.phone,
    required this.region,
    required this.city,
    required this.addressLine,
    required this.postalCode,
  });

  final String fullName;
  final String phone;
  final String region;
  final String city;
  final String addressLine;
  final String postalCode;

  Map<String, dynamic> toJson() {
    return {
      'full_name': fullName,
      'phone': phone,
      'region': region,
      'city': city,
      'address_line': addressLine,
      'postal_code': postalCode,
    };
  }

  factory ShippingAddress.fromJson(Map<String, dynamic> json) {
    return ShippingAddress(
      fullName: json['full_name'] as String? ?? '',
      phone: json['phone'] as String? ?? '',
      region: json['region'] as String? ?? '',
      city: json['city'] as String? ?? '',
      addressLine: json['address_line'] as String? ?? '',
      postalCode: json['postal_code'] as String? ?? '',
    );
  }
}

class OrderTotals {
  const OrderTotals({
    required this.subtotal,
    required this.shipping,
    required this.discount,
    required this.tax,
    required this.total,
    this.currency = 'UZS',
  });

  final MoneyAmount subtotal;
  final MoneyAmount shipping;
  final MoneyAmount discount;
  final MoneyAmount tax;
  final MoneyAmount total;
  final String currency;

  factory OrderTotals.fromJson(Map<String, dynamic> json) {
    return OrderTotals(
      subtotal: MoneyAmount.fromJson(json['subtotal'] as Map<String, dynamic>),
      shipping: MoneyAmount.fromJson(json['shipping'] as Map<String, dynamic>),
      discount: MoneyAmount.fromJson(json['discount'] as Map<String, dynamic>),
      tax: MoneyAmount.fromJson(json['tax'] as Map<String, dynamic>),
      total: MoneyAmount.fromJson(json['total'] as Map<String, dynamic>),
      currency: json['currency'] as String? ?? 'UZS',
    );
  }
}

class OrderItem {
  const OrderItem({
    required this.id,
    required this.productTitle,
    this.variantName,
    required this.quantity,
    required this.unitPrice,
    required this.lineTotal,
  });

  final String id;
  final String productTitle;
  final String? variantName;
  final int quantity;
  final MoneyAmount unitPrice;
  final MoneyAmount lineTotal;

  factory OrderItem.fromJson(Map<String, dynamic> json) {
    return OrderItem(
      id: _idToString(json['id']) ?? '',
      productTitle: json['product_title'] as String? ?? 'Product',
      variantName: json['variant_name'] as String?,
      quantity: _toInt(json['quantity'], fallback: 1),
      unitPrice: MoneyAmount.fromJson(json['unit_price'] as Map<String, dynamic>),
      lineTotal: MoneyAmount.fromJson(json['line_total'] as Map<String, dynamic>),
    );
  }
}

class Order {
  const Order({
    required this.id,
    required this.orderNumber,
    required this.status,
    required this.version,
    required this.totals,
    this.items = const [],
    this.shippingAddress,
    this.createdAt,
    this.paymentStatus,
    this.paymentMethod,
    this.paymentProvider,
    this.paymentReference,
  });

  final String id;
  final String orderNumber;
  final String status;
  final int version;
  final OrderTotals totals;
  final List<OrderItem> items;
  final ShippingAddress? shippingAddress;
  final String? createdAt;
  final String? paymentStatus;
  final String? paymentMethod;
  final String? paymentProvider;
  final String? paymentReference;

  bool get isAwaitingPayment => status == 'awaiting_payment';

  bool get canRequestRefund =>
      status == 'paid' || status == 'delivered' || status == 'completed';

  factory Order.fromJson(Map<String, dynamic> json) {
    final itemsJson = json['items'] as List<dynamic>? ?? [];
    final shipment = json['shipment'] as Map<String, dynamic>?;
    final addressJson = shipment?['address'] as Map<String, dynamic>?;
    final payment = json['payment'] as Map<String, dynamic>?;

    return Order(
      id: json['id'] as String,
      orderNumber: json['order_number'] as String? ?? '',
      status: json['status'] as String? ?? 'pending',
      version: _toInt(json['version'], fallback: 1),
      totals: OrderTotals.fromJson(json['totals'] as Map<String, dynamic>),
      items: itemsJson
          .map((item) => OrderItem.fromJson(item as Map<String, dynamic>))
          .toList(),
      shippingAddress:
          addressJson != null ? ShippingAddress.fromJson(addressJson) : null,
      createdAt: json['created_at'] as String?,
      paymentStatus: payment?['status'] as String?,
      paymentMethod: payment?['method'] as String?,
      paymentProvider: payment?['provider'] as String?,
      paymentReference: payment?['reference'] as String?,
    );
  }
}

class OrderPage {
  const OrderPage({
    required this.orders,
    required this.currentPage,
    required this.lastPage,
    required this.total,
  });

  final List<Order> orders;
  final int currentPage;
  final int lastPage;
  final int total;

  bool get hasMore => currentPage < lastPage;
}

class CheckoutResult {
  const CheckoutResult({
    required this.order,
    this.paymentUrl,
  });

  final Order order;
  final String? paymentUrl;
}
