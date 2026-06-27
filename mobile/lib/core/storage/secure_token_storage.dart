import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:livecommerce_mobile/core/constants/storage_keys.dart';

class SecureTokenStorage {
  SecureTokenStorage({FlutterSecureStorage? storage})
      : _storage = storage ?? const FlutterSecureStorage();

  final FlutterSecureStorage _storage;

  Future<void> saveTokens({
    required String accessToken,
    required String refreshToken,
  }) async {
    await _storage.write(key: StorageKeys.accessToken, value: accessToken);
    await _storage.write(key: StorageKeys.refreshToken, value: refreshToken);
  }

  Future<String?> readAccessToken() {
    return _storage.read(key: StorageKeys.accessToken);
  }

  Future<String?> readRefreshToken() {
    return _storage.read(key: StorageKeys.refreshToken);
  }

  Future<void> clearTokens() async {
    await _storage.delete(key: StorageKeys.accessToken);
    await _storage.delete(key: StorageKeys.refreshToken);
  }
}
