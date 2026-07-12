import 'package:flutter/foundation.dart';

class ApiConstants {
  ApiConstants._();

  /// Cloud API (Render). Override with `--dart-define=API_BASE_URL=...`
  /// or `--dart-define=USE_LOCAL_API=true` for localhost / emulator.
  static const String cloudBaseUrl =
      'https://livecommerce-api.onrender.com/api/v1';

  static String get baseUrl {
    const fromEnv = String.fromEnvironment('API_BASE_URL');
    if (fromEnv.isNotEmpty) {
      return fromEnv;
    }

    const useLocal = bool.fromEnvironment('USE_LOCAL_API', defaultValue: false);
    if (useLocal) {
      if (kIsWeb || defaultTargetPlatform == TargetPlatform.windows) {
        return 'http://localhost:8080/api/v1';
      }
      return 'http://10.0.2.2:8080/api/v1';
    }

    return cloudBaseUrl;
  }

  static const Duration connectTimeout = Duration(seconds: 30);
  static const Duration receiveTimeout = Duration(seconds: 60);
}
