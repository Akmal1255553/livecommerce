import 'package:intl/intl.dart';

class MoneyAmount {
  const MoneyAmount({
    required this.amount,
    this.currency = 'UZS',
  });

  final int amount;
  final String currency;

  factory MoneyAmount.fromJson(Map<String, dynamic> json) {
    final amount = json['amount'];
    return MoneyAmount(
      amount: amount is int
          ? amount
          : amount is num
              ? amount.toInt()
              : int.tryParse(amount?.toString() ?? '') ?? 0,
      currency: json['currency'] as String? ?? 'UZS',
    );
  }

  String format() {
    final formatter = NumberFormat('#,###', 'en_US');
    return '${formatter.format(amount)} $currency';
  }
}
