import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:livecommerce_mobile/features/auth/presentation/providers/auth_providers.dart';
import 'package:livecommerce_mobile/features/push/data/push_repository.dart';

final pushRepositoryProvider = Provider<PushRepository>((ref) {
  return PushRepository(ref.watch(authDioProvider));
});

/// Wires FCM to the backend:
///  1. initializes Firebase (fails gracefully when no Firebase config is
///     present in the build — push is simply disabled),
///  2. requests notification permission,
///  3. registers the device token via `POST /devices`.
///
/// Background / terminated pushes are shown by the OS automatically; the app
/// only needs the registered token for the backend to address it.
class PushService {
  PushService(this._repository);

  final PushRepository _repository;
  bool _configured = false;

  Future<void> setup() async {
    if (_configured) {
      return;
    }
    try {
      await Firebase.initializeApp();
      final messaging = FirebaseMessaging.instance;

      await messaging.requestPermission(
        alert: true,
        badge: true,
        sound: true,
      );

      final token = await messaging.getToken();
      if (token != null) {
        await _repository.registerToken(
          token: token,
          platform: defaultTargetPlatform == TargetPlatform.iOS ? 'ios' : 'android',
        );
      }

      _configured = true;
    } catch (_) {
      // Firebase is not configured for this build (no google-services.json /
      // GoogleService-Info.plist) — push stays a no-op.
    }
  }
}

final pushServiceProvider = Provider<PushService>((ref) {
  return PushService(ref.watch(pushRepositoryProvider));
});
