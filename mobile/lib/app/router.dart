import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:livecommerce_mobile/features/auth/presentation/providers/auth_providers.dart';
import 'package:livecommerce_mobile/features/auth/presentation/screens/login_screen.dart';
import 'package:livecommerce_mobile/features/auth/presentation/screens/otp_screen.dart';
import 'package:livecommerce_mobile/features/auth/presentation/screens/profile_screen.dart';
import 'package:livecommerce_mobile/features/auth/presentation/screens/register_screen.dart';
import 'package:livecommerce_mobile/features/auth/presentation/screens/settings_screen.dart';
import 'package:livecommerce_mobile/features/commerce/domain/entities/order.dart';
import 'package:livecommerce_mobile/features/commerce/presentation/screens/cart_screen.dart';
import 'package:livecommerce_mobile/features/commerce/presentation/screens/checkout_screen.dart';
import 'package:livecommerce_mobile/features/commerce/presentation/screens/order_detail_screen.dart';
import 'package:livecommerce_mobile/features/commerce/presentation/screens/order_success_screen.dart';
import 'package:livecommerce_mobile/features/commerce/presentation/screens/orders_screen.dart';
import 'package:livecommerce_mobile/features/commerce/presentation/screens/payment_screen.dart';
import 'package:livecommerce_mobile/features/commerce/presentation/screens/product_screen.dart';
import 'package:livecommerce_mobile/features/commerce/presentation/screens/refund_request_screen.dart';
import 'package:livecommerce_mobile/features/feed/presentation/screens/feed_screen.dart';
import 'package:livecommerce_mobile/features/live/presentation/screens/go_live_screen.dart';
import 'package:livecommerce_mobile/features/live/presentation/screens/live_discovery_screen.dart';
import 'package:livecommerce_mobile/features/live/presentation/screens/live_replay_screen.dart';
import 'package:livecommerce_mobile/features/live/presentation/screens/live_room_screen.dart';
import 'package:livecommerce_mobile/features/seller/presentation/screens/public_store_screen.dart';
import 'package:livecommerce_mobile/features/seller/presentation/screens/seller_apply_screen.dart';
import 'package:livecommerce_mobile/features/seller/presentation/screens/seller_dashboard_screen.dart';
import 'package:livecommerce_mobile/features/seller/presentation/screens/seller_live_analytics_screen.dart';
import 'package:livecommerce_mobile/features/seller/presentation/screens/seller_live_session_analytics_screen.dart';
import 'package:livecommerce_mobile/features/seller/presentation/screens/seller_order_detail_screen.dart';
import 'package:livecommerce_mobile/features/seller/presentation/screens/seller_orders_screen.dart';
import 'package:livecommerce_mobile/features/seller/presentation/screens/seller_product_form_screen.dart';
import 'package:livecommerce_mobile/features/seller/presentation/screens/seller_products_screen.dart';

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
        GoRoute(
          path: '/products/:id',
          builder: (context, state) {
            final id = state.pathParameters['id']!;
            return ProductScreen(productId: id);
          },
        ),
        GoRoute(
          path: '/cart',
          builder: (context, state) => const CartScreen(),
        ),
        GoRoute(
          path: '/checkout',
          builder: (context, state) => const CheckoutScreen(),
        ),
        GoRoute(
          path: '/payment/:id',
          builder: (context, state) {
            final orderId = state.pathParameters['id']!;
            final extra = state.extra;
            Order? order;
            String? paymentUrl;
            if (extra is Map) {
              final rawOrder = extra['order'];
              if (rawOrder is Order) {
                order = rawOrder;
              }
              paymentUrl = extra['paymentUrl'] as String?;
            } else if (extra is Order) {
              order = extra;
            }
            return PaymentScreen(
              orderId: orderId,
              initialOrder: order,
              paymentUrl: paymentUrl,
            );
          },
        ),
        GoRoute(
          path: '/order-success/:id',
          builder: (context, state) {
            final orderId = state.pathParameters['id']!;
            final initialOrder = state.extra is Order ? state.extra as Order : null;
            return OrderSuccessScreen(
              orderId: orderId,
              initialOrder: initialOrder,
            );
          },
        ),
        GoRoute(
          path: '/orders',
          builder: (context, state) => const OrdersScreen(),
        ),
        GoRoute(
          path: '/orders/:id/refund',
          builder: (context, state) {
            final id = state.pathParameters['id']!;
            return RefundRequestScreen(orderId: id);
          },
        ),
        GoRoute(
          path: '/orders/:id',
          builder: (context, state) {
            final id = state.pathParameters['id']!;
            return OrderDetailScreen(orderId: id);
          },
        ),
        GoRoute(
          path: '/seller/apply',
          builder: (context, state) => const SellerApplyScreen(),
        ),
        GoRoute(
          path: '/seller',
          builder: (context, state) => const SellerDashboardScreen(),
        ),
        GoRoute(
          path: '/seller/live/analytics',
          builder: (context, state) => const SellerLiveAnalyticsScreen(),
        ),
        GoRoute(
          path: '/seller/live/:id/analytics',
          builder: (context, state) {
            final id = state.pathParameters['id']!;
            return SellerLiveSessionAnalyticsScreen(sessionId: id);
          },
        ),
        GoRoute(
          path: '/seller/products',
          builder: (context, state) => const SellerProductsScreen(),
        ),
        GoRoute(
          path: '/seller/products/new',
          builder: (context, state) => const SellerProductFormScreen(),
        ),
        GoRoute(
          path: '/seller/orders',
          builder: (context, state) => const SellerOrdersScreen(),
        ),
        GoRoute(
          path: '/seller/orders/:id',
          builder: (context, state) {
            final id = state.pathParameters['id']!;
            return SellerOrderDetailScreen(orderId: id);
          },
        ),
        GoRoute(
          path: '/stores/:slug',
          builder: (context, state) {
            final slug = state.pathParameters['slug']!;
            return PublicStoreScreen(slug: slug);
          },
        ),
        GoRoute(
          path: '/live',
          builder: (context, state) => const LiveDiscoveryScreen(),
        ),
        GoRoute(
          path: '/live/go',
          builder: (context, state) => const GoLiveScreen(),
        ),
        GoRoute(
          path: '/live/replay/:id',
          builder: (context, state) {
            final id = state.pathParameters['id']!;
            return LiveReplayScreen(sessionId: id);
          },
        ),
        GoRoute(
          path: '/live/:id',
          builder: (context, state) {
            final id = state.pathParameters['id']!;
            final host = state.uri.queryParameters['host'] == '1';
            return LiveRoomScreen(sessionId: id, asHost: host);
          },
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
