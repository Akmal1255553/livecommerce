import 'package:flutter/material.dart';
import 'package:flutter_gen/gen_l10n/app_localizations.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:livecommerce_mobile/features/auth/presentation/screens/login_screen.dart';
import 'package:livecommerce_mobile/features/auth/presentation/screens/profile_screen.dart';
import 'package:livecommerce_mobile/features/auth/presentation/screens/register_screen.dart';

Widget _wrap(Widget child) {
  return ProviderScope(
    child: MaterialApp(
      locale: const Locale('uz'),
      localizationsDelegates: const [
        AppLocalizations.delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
      supportedLocales: AppLocalizations.supportedLocales,
      home: child,
    ),
  );
}

void main() {
  testWidgets('login form renders required fields', (tester) async {
    await tester.pumpWidget(_wrap(const LoginScreen()));

    expect(find.byKey(const Key('login_field')), findsOneWidget);
    expect(find.byKey(const Key('password_field')), findsOneWidget);
    expect(find.text('Kirish'), findsOneWidget);
  });

  testWidgets('register form renders email and phone fields', (tester) async {
    await tester.pumpWidget(_wrap(const RegisterScreen()));

    expect(find.byKey(const Key('username_field')), findsOneWidget);
    expect(find.byKey(const Key('email_field')), findsOneWidget);
    expect(find.byKey(const Key('password_field')), findsOneWidget);
    expect(find.byKey(const Key('password_confirm_field')), findsOneWidget);
  });

  testWidgets('profile form shows auth required when logged out', (tester) async {
    await tester.pumpWidget(_wrap(const ProfileScreen()));

    expect(find.text('Davom etish uchun tizimga kiring'), findsOneWidget);
  });
}
