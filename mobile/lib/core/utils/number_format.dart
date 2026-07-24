import 'package:intl/intl.dart';

/// Compact counters for social metrics: 999, 1.2K, 3.4M.
String formatCount(int value) {
  if (value < 1000) {
    return '$value';
  }
  if (value < 1000000) {
    final k = value / 1000;
    return '${_trim(k)}K';
  }
  final m = value / 1000000;
  return '${_trim(m)}M';
}

String _trim(double value) {
  final rounded = (value * 10).round() / 10;
  if (rounded >= 100 || rounded == rounded.roundToDouble()) {
    return rounded.round().toString();
  }
  return rounded.toStringAsFixed(1);
}

final _priceFormat = NumberFormat('#,###');

/// Money for display: `1 250 000 UZS`.
String formatPrice(num amount, String currency) {
  return '${_priceFormat.format(amount.toInt())} $currency';
}
