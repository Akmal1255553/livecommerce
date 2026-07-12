import 'package:intl/intl.dart';

class MoneyAmount {
  const MoneyAmount({
    required this.amount,
    this.currency = 'UZS',
  });

  final int amount;
  final String currency;

  factory MoneyAmount.fromJson(Map<String, dynamic> json) {
    return MoneyAmount(
      amount: json['amount'] as int? ?? 0,
      currency: json['currency'] as String? ?? 'UZS',
    );
  }

  String format() {
    final formatter = NumberFormat('#,###', 'en_US');
    return '${formatter.format(amount)} $currency';
  }
}
