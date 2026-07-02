import 'package:flutter/foundation.dart';

/// Local dev shortcuts. Disabled in release builds.
abstract final class DevConfig {
  static bool get bypassAuth => kDebugMode;
}
