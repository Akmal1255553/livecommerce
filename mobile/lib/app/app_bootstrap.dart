import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:livecommerce_mobile/core/config/dev_config.dart';
import 'package:livecommerce_mobile/app/app.dart';
import 'package:livecommerce_mobile/features/auth/presentation/providers/auth_providers.dart';
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

  if (DevConfig.bypassAuth && !container.read(authNotifierProvider).isAuthenticated) {
    container.read(authNotifierProvider.notifier).enterDevGuestMode();
  }

  runApp(
    UncontrolledProviderScope(
      container: container,
      child: const LiveCommerceApp(),
    ),
  );
}
