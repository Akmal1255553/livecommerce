import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:livecommerce_mobile/app/app.dart';
import 'package:livecommerce_mobile/core/config/dev_config.dart';
import 'package:livecommerce_mobile/features/auth/presentation/providers/auth_providers.dart';
import 'package:sentry_flutter/sentry_flutter.dart';
import 'package:shared_preferences/shared_preferences.dart';

Future<void> bootstrap() async {
  WidgetsFlutterBinding.ensureInitialized();

  final prefs = await SharedPreferences.getInstance();

  final container = ProviderContainer(
    overrides: [
      sharedPreferencesProvider.overrideWithValue(prefs),
    ],
  );

  await container.read(authNotifierProvider.notifier).restoreSession();

  if (DevConfig.bypassAuth &&
      !container.read(authNotifierProvider).isAuthenticated) {
    container.read(authNotifierProvider.notifier).enterDevGuestMode();
  }

  Future<void> run() async {
    runApp(
      UncontrolledProviderScope(
        container: container,
        child: const LiveCommerceApp(),
      ),
    );
  }

  const dsn = String.fromEnvironment('SENTRY_DSN');
  if (dsn.isEmpty) {
    await run();
    return;
  }

  await SentryFlutter.init(
    (options) {
      options.dsn = dsn;
      const envOverride =
          String.fromEnvironment('SENTRY_ENVIRONMENT', defaultValue: '');
      options.environment = envOverride.isNotEmpty
          ? envOverride
          : (kReleaseMode ? 'production' : 'development');
      options.tracesSampleRate = 0.1;
      options.sendDefaultPii = false;
    },
    appRunner: run,
  );
}
