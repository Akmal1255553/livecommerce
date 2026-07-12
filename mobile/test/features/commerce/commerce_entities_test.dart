import 'package:flutter_test/flutter_test.dart';
import 'package:livecommerce_mobile/features/commerce/domain/entities/cart.dart';
import 'package:livecommerce_mobile/features/commerce/domain/entities/money_amount.dart';

void main() {
  group('MoneyAmount', () {
    test('parses backend money json', () {
      final money = MoneyAmount.fromJson({'amount': 125000, 'currency': 'UZS'});
      expect(money.amount, 125000);
      expect(money.currency, 'UZS');
      expect(money.format(), '125,000 UZS');
    });
  });

  group('Cart', () {
    test('parses cart response with line items', () {
      final cart = Cart.fromJson({
        'id': 'cart-1',
        'type': 'user',
        'version': 2,
        'items': [
          {
            'id': 'line-1',
            'product': {
              'id': 'prod-1',
              'title': 'Test product',
              'price': 50000,
              'currency': 'UZS',
              'status': 'active',
              'is_purchasable': true,
            },
            'variant': null,
            'quantity': 1,
            'unit_price': {'amount': 50000, 'currency': 'UZS'},
            'line_total': {'amount': 50000, 'currency': 'UZS'},
            'discount_amount': {'amount': 0, 'currency': 'UZS'},
          },
        ],
        'summary': {
          'subtotal': {'amount': 50000, 'currency': 'UZS'},
          'discount_total': {'amount': 0, 'currency': 'UZS'},
          'shipping_estimate': {'amount': 15000, 'currency': 'UZS'},
          'currency': 'UZS',
          'item_count': 1,
        },
      });

      expect(cart.version, 2);
      expect(cart.items, hasLength(1));
      expect(cart.summary.itemCount, 1);
      expect(cart.summary.subtotal.amount, 50000);
    });
  });
}
