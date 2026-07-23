import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:livecommerce_mobile/app/app.dart';
import 'package:livecommerce_mobile/core/config/dev_config.dart';
import 'package:livecommerce_mobile/features/auth/presentation/providers/auth_providers.dart';
import 'package:sentry_flutter/sentry_flutter.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Default Flutter Sentry DSN (project flutter-h1). Override with
/// `--dart-define=SENTRY_DSN=` (empty disables) or a different DSN.
const String _defaultSentryDsn =
    'https://78acd4c6ce126ae3b30007d0d3481044@o4511773106110464.ingest.us.sentry.io/4511773139599360';

Future<void> bootstrap() async {
  // Must use Sentry's binding when initializing before SentryFlutter.init,
  // otherwise slow/frozen frame tracking is disabled.
  SentryWidgetsFlutterBinding.ensureInitialized();

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
        child: SentryWidget(
          child: const LiveCommerceApp(),
        ),
      ),
    );
  }

  // Empty `--dart-define=SENTRY_DSN=` disables; otherwise use define or default.
  const fromEnv = String.fromEnvironment('SENTRY_DSN', defaultValue: '__unset__');
  final dsn = fromEnv == '__unset__'
      ? _defaultSentryDsn
      : (fromEnv.isEmpty ? null : fromEnv);

  if (dsn == null) {
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
