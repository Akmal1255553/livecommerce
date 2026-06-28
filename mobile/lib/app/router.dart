import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:livecommerce_mobile/features/auth/presentation/providers/auth_providers.dart';
import 'package:livecommerce_mobile/features/auth/presentation/screens/login_screen.dart';
import 'package:livecommerce_mobile/features/auth/presentation/screens/otp_screen.dart';
import 'package:livecommerce_mobile/features/auth/presentation/screens/profile_screen.dart';
import 'package:livecommerce_mobile/features/auth/presentation/screens/register_screen.dart';
import 'package:livecommerce_mobile/features/auth/presentation/screens/settings_screen.dart';
import 'package:livecommerce_mobile/features/feed/presentation/screens/feed_screen.dart';

class AppRouter {
  AppRouter._();

  static GoRouter create(Ref ref) {
    return GoRouter(
      initialLocation: '/splash',
      refreshListenable: _AuthRefreshListenable(ref),
      redirect: (context, state) {
        final auth = ref.read(authNotifierProvider);
        final isAuth = auth.isAuthenticated;
        final isLoading = auth.isLoading;
        final path = state.matchedLocation;
        final isAuthRoute = path == '/login' ||
            path == '/register' ||
            path.startsWith('/otp');

        if (path == '/splash') {
          if (isLoading) {
            return null;
          }
          return isAuth ? '/home' : '/login';
        }

        if (!isAuth && !isAuthRoute) {
          return '/login';
        }

        if (isAuth && (path == '/login' || path == '/register')) {
          return '/home';
        }

        return null;
      },
      routes: [
        GoRoute(
          path: '/splash',
          builder: (context, state) => const _SplashScreen(),
        ),
        GoRoute(
          path: '/login',
          builder: (context, state) => const LoginScreen(),
        ),
        GoRoute(
          path: '/register',
          builder: (context, state) => const RegisterScreen(),
        ),
        GoRoute(
          path: '/otp',
          builder: (context, state) {
            final phone = state.extra as String? ?? '';
            return OtpScreen(phone: phone);
          },
        ),
        GoRoute(
          path: '/home',
          builder: (context, state) => const FeedScreen(),
        ),
        GoRoute(
          path: '/profile',
          builder: (context, state) => const ProfileScreen(),
        ),
        GoRoute(
          path: '/settings',
          builder: (context, state) => const SettingsScreen(),
        ),
      ],
    );
  }
}

class _AuthRefreshListenable extends ChangeNotifier {
  _AuthRefreshListenable(this._ref) {
    _ref.listen<AuthState>(authNotifierProvider, (_, __) => notifyListeners());
  }

  final Ref _ref;
}

class _SplashScreen extends ConsumerWidget {
  const _SplashScreen();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return const Scaffold(
      body: Center(child: CircularProgressIndicator()),
    );
  }
}
