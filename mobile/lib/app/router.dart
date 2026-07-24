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
import 'package:livecommerce_mobile/features/messaging/domain/entities/conversation.dart';
import 'package:livecommerce_mobile/features/messaging/presentation/screens/chat_screen.dart';
import 'package:livecommerce_mobile/features/messaging/presentation/screens/conversations_screen.dart';
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
import 'package:livecommerce_mobile/features/wallet/presentation/screens/wallet_screen.dart';
import 'package:livecommerce_mobile/shared/navigation/page_transitions.dart';

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
          pageBuilder: (context, state) => AppPageTransitions.fadeSlide(
            key: state.pageKey,
            child: const LoginScreen(),
          ),
        ),
        GoRoute(
          path: '/register',
          pageBuilder: (context, state) => AppPageTransitions.fadeSlide(
            key: state.pageKey,
            child: const RegisterScreen(),
          ),
        ),
        GoRoute(
          path: '/otp',
          pageBuilder: (context, state) {
            final phone = state.extra as String? ?? '';
            return AppPageTransitions.fadeSlide(
              key: state.pageKey,
              child: OtpScreen(phone: phone),
            );
          },
        ),
        GoRoute(
          path: '/home',
          pageBuilder: (context, state) => AppPageTransitions.fadeSlide(
            key: state.pageKey,
            child: const FeedScreen(),
          ),
        ),
        GoRoute(
          path: '/profile',
          pageBuilder: (context, state) => AppPageTransitions.fadeSlide(
            key: state.pageKey,
            child: const ProfileScreen(),
          ),
        ),
        GoRoute(
          path: '/settings',
          pageBuilder: (context, state) => AppPageTransitions.fadeSlide(
            key: state.pageKey,
            child: const SettingsScreen(),
          ),
        ),
        GoRoute(
          path: '/products/:id',
          pageBuilder: (context, state) {
            final id = state.pathParameters['id']!;
            return AppPageTransitions.fadeSlide(
              key: state.pageKey,
              child: ProductScreen(productId: id),
            );
          },
        ),
        GoRoute(
          path: '/cart',
          pageBuilder: (context, state) => AppPageTransitions.fadeSlide(
            key: state.pageKey,
            child: const CartScreen(),
          ),
        ),
        GoRoute(
          path: '/checkout',
          pageBuilder: (context, state) => AppPageTransitions.slideUp(
            key: state.pageKey,
            child: const CheckoutScreen(),
          ),
        ),
        GoRoute(
          path: '/payment/:id',
          pageBuilder: (context, state) {
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
            return AppPageTransitions.slideUp(
              key: state.pageKey,
              child: PaymentScreen(
                orderId: orderId,
                initialOrder: order,
                paymentUrl: paymentUrl,
              ),
            );
          },
        ),
        GoRoute(
          path: '/order-success/:id',
          pageBuilder: (context, state) {
            final orderId = state.pathParameters['id']!;
            final initialOrder =
                state.extra is Order ? state.extra as Order : null;
            return AppPageTransitions.fadeSlide(
              key: state.pageKey,
              child: OrderSuccessScreen(
                orderId: orderId,
                initialOrder: initialOrder,
              ),
            );
          },
        ),
        GoRoute(
          path: '/orders',
          pageBuilder: (context, state) => AppPageTransitions.fadeSlide(
            key: state.pageKey,
            child: const OrdersScreen(),
          ),
        ),
        GoRoute(
          path: '/wallet',
          pageBuilder: (context, state) => AppPageTransitions.fadeSlide(
            key: state.pageKey,
            child: const WalletScreen(),
          ),
        ),
        GoRoute(
          path: '/orders/:id/refund',
          pageBuilder: (context, state) {
            final id = state.pathParameters['id']!;
            return AppPageTransitions.slideUp(
              key: state.pageKey,
              child: RefundRequestScreen(orderId: id),
            );
          },
        ),
        GoRoute(
          path: '/orders/:id',
          pageBuilder: (context, state) {
            final id = state.pathParameters['id']!;
            return AppPageTransitions.fadeSlide(
              key: state.pageKey,
              child: OrderDetailScreen(orderId: id),
            );
          },
        ),
        GoRoute(
          path: '/seller/apply',
          pageBuilder: (context, state) => AppPageTransitions.fadeSlide(
            key: state.pageKey,
            child: const SellerApplyScreen(),
          ),
        ),
        GoRoute(
          path: '/seller',
          pageBuilder: (context, state) => AppPageTransitions.fadeSlide(
            key: state.pageKey,
            child: const SellerDashboardScreen(),
          ),
        ),
        GoRoute(
          path: '/seller/live/analytics',
          pageBuilder: (context, state) => AppPageTransitions.fadeSlide(
            key: state.pageKey,
            child: const SellerLiveAnalyticsScreen(),
          ),
        ),
        GoRoute(
          path: '/seller/live/:id/analytics',
          pageBuilder: (context, state) {
            final id = state.pathParameters['id']!;
            return AppPageTransitions.fadeSlide(
              key: state.pageKey,
              child: SellerLiveSessionAnalyticsScreen(sessionId: id),
            );
          },
        ),
        GoRoute(
          path: '/seller/products',
          pageBuilder: (context, state) => AppPageTransitions.fadeSlide(
            key: state.pageKey,
            child: const SellerProductsScreen(),
          ),
        ),
        GoRoute(
          path: '/seller/products/new',
          pageBuilder: (context, state) => AppPageTransitions.slideUp(
            key: state.pageKey,
            child: const SellerProductFormScreen(),
          ),
        ),
        GoRoute(
          path: '/seller/orders',
          pageBuilder: (context, state) => AppPageTransitions.fadeSlide(
            key: state.pageKey,
            child: const SellerOrdersScreen(),
          ),
        ),
        GoRoute(
          path: '/seller/orders/:id',
          pageBuilder: (context, state) {
            final id = state.pathParameters['id']!;
            return AppPageTransitions.fadeSlide(
              key: state.pageKey,
              child: SellerOrderDetailScreen(orderId: id),
            );
          },
        ),
        GoRoute(
          path: '/stores/:slug',
          pageBuilder: (context, state) {
            final slug = state.pathParameters['slug']!;
            return AppPageTransitions.fadeSlide(
              key: state.pageKey,
              child: PublicStoreScreen(slug: slug),
            );
          },
        ),
        GoRoute(
          path: '/conversations',
          pageBuilder: (context, state) => AppPageTransitions.fadeSlide(
            key: state.pageKey,
            child: const ConversationsScreen(),
          ),
        ),
        GoRoute(
          path: '/conversations/:id',
          pageBuilder: (context, state) {
            final id = state.pathParameters['id']!;
            final conv = state.extra is Conversation
                ? state.extra as Conversation
                : null;
            return AppPageTransitions.fadeSlide(
              key: state.pageKey,
              child: ChatScreen(
                conversationId: id,
                initialConversation: conv,
              ),
            );
          },
        ),
        GoRoute(
          path: '/live',
          pageBuilder: (context, state) => AppPageTransitions.fadeSlide(
            key: state.pageKey,
            child: const LiveDiscoveryScreen(),
          ),
        ),
        GoRoute(
          path: '/live/go',
          pageBuilder: (context, state) => AppPageTransitions.slideUp(
            key: state.pageKey,
            child: const GoLiveScreen(),
          ),
        ),
        GoRoute(
          path: '/live/replay/:id',
          pageBuilder: (context, state) {
            final id = state.pathParameters['id']!;
            return AppPageTransitions.fadeSlide(
              key: state.pageKey,
              child: LiveReplayScreen(sessionId: id),
            );
          },
        ),
        GoRoute(
          path: '/live/:id',
          pageBuilder: (context, state) {
            final id = state.pathParameters['id']!;
            final host = state.uri.queryParameters['host'] == '1';
            return AppPageTransitions.fadeSlide(
              key: state.pageKey,
              child: LiveRoomScreen(sessionId: id, asHost: host),
            );
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
