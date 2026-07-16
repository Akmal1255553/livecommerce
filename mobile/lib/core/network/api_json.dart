Map<String, dynamic> unwrapApiResource(Object? value) {
  if (value is! Map<String, dynamic>) {
    throw const FormatException('Expected API resource object.');
  }

  final nested = value['data'];
  if (nested is Map<String, dynamic> &&
      (value.containsKey('type') || value.length <= 2)) {
    return nested;
  }

  return value;
}
