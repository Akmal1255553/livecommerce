import 'dart:math';

import 'package:livecommerce_mobile/core/constants/storage_keys.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Stable analytics session UUID for engagement APIs (`session_id`).
///
/// Backend requires a UUID on like / bookmark / view / comment POSTs.
/// Persisted so the same client keeps one session across app restarts.
class AnalyticsSession {
  AnalyticsSession(this._prefs);

  final SharedPreferences _prefs;
  String? _cached;

  Future<String> id() async {
    final existing = _cached ?? _prefs.getString(StorageKeys.analyticsSessionId);
    if (existing != null && existing.isNotEmpty) {
      _cached = existing;
      return existing;
    }

    final created = _uuidV4();
    await _prefs.setString(StorageKeys.analyticsSessionId, created);
    _cached = created;
    return created;
  }

  static String _uuidV4() {
    final random = Random.secure();
    final bytes = List<int>.generate(16, (_) => random.nextInt(256));
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    final hex = bytes.map((b) => b.toRadixString(16).padLeft(2, '0')).join();
    return '${hex.substring(0, 8)}-${hex.substring(8, 12)}-'
        '${hex.substring(12, 16)}-${hex.substring(16, 20)}-${hex.substring(20)}';
  }
}
