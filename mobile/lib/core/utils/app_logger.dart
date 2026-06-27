import 'package:flutter/foundation.dart';

class AppLogger {
  AppLogger._();

  static void debug(String message, {Map<String, Object?>? context}) {
    if (kDebugMode) {
      debugPrint('[DEBUG] $message ${context ?? ''}');
    }
  }

  static void info(String message, {Map<String, Object?>? context}) {
    if (kDebugMode) {
      debugPrint('[INFO] $message ${context ?? ''}');
    }
  }

  static void error(String message, {Object? error, StackTrace? stackTrace}) {
    debugPrint('[ERROR] $message');
    if (error != null) {
      debugPrint(error.toString());
    }
    if (stackTrace != null) {
      debugPrintStack(stackTrace: stackTrace);
    }
  }
}
