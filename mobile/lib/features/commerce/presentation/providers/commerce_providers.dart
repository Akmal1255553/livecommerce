import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:livecommerce_mobile/core/errors/error_handler.dart';
import 'package:livecommerce_mobile/features/auth/presentation/providers/auth_providers.dart';
import 'package:livecommerce_mobile/features/commerce/data/commerce_repository.dart';
import 'package:livecommerce_mobile/features/commerce/domain/entities/cart.dart';
import 'package:livecommerce_mobile/features/commerce/domain/entities/order.dart';
import 'package:livecommerce_mobile/features/commerce/domain/entities/product_detail.dart';

final commerceRepositoryProvider = Provider<CommerceRepository>((ref) {
  ref.watch(authRepositoryProvider);
  final dio = ref.watch(authDioProvider);
  return CommerceRepository(remote: CommerceRemoteDataSource(dio));
});

class CartState {
  const CartState({
    this.cart,
    this.isLoading = false,
    this.isMutating = false,
    this.error,
  });

  final Cart? cart;
  final bool isLoading;
  final bool isMutating;
  final String? error;

  int get itemCount => cart?.summary.itemCount ?? 0;

  CartState copyWith({
    Cart? cart,
    bool? isLoading,
    bool? isMutating,
    String? error,
    bool clearError = false,
  }) {
    return CartState(
      cart: cart ?? this.cart,
      isLoading: isLoading ?? this.isLoading,
      isMutating: isMutating ?? this.isMutating,
      error: clearError ? null : (error ?? this.error),
    );
  }
}

class CartNotifier extends StateNotifier<CartState> {
  CartNotifier(this._repository) : super(const CartState());

  final CommerceRepository _repository;

  Future<void> load() async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final cart = await _repository.getCart();
      state = state.copyWith(cart: cart, isLoading: false);
    } catch (error) {
      state = state.copyWith(isLoading: false, error: describeFailure(error));
    }
  }

  Future<bool> addProduct({
    required String productId,
    String? variantId,
    int quantity = 1,
  }) async {
    state = state.copyWith(isMutating: true, clearError: true);
    try {
      final cart = await _repository.addToCart(
        productId: productId,
        variantId: variantId,
        quantity: quantity,
      );
      state = state.copyWith(cart: cart, isMutating: false);
      return true;
    } catch (error) {
      state = state.copyWith(isMutating: false, error: describeFailure(error));
      return false;
    }
  }

  Future<bool> updateQuantity(String itemId, int quantity) async {
    state = state.copyWith(isMutating: true, clearError: true);
    try {
      final cart = quantity <= 0
          ? await _repository.removeCartItem(itemId)
          : await _repository.updateCartItem(itemId: itemId, quantity: quantity);
      state = state.copyWith(cart: cart, isMutating: false);
      return true;
    } catch (error) {
      state = state.copyWith(isMutating: false, error: describeFailure(error));
      return false;
    }
  }

  Future<bool> removeItem(String itemId) => updateQuantity(itemId, 0);
}

final cartNotifierProvider =
    StateNotifierProvider<CartNotifier, CartState>((ref) {
  return CartNotifier(ref.watch(commerceRepositoryProvider));
});

final productDetailProvider =
    FutureProvider.family<ProductDetail, String>((ref, id) async {
  return ref.watch(commerceRepositoryProvider).getProduct(id);
});

class OrdersState {
  const OrdersState({
    this.orders = const [],
    this.isLoading = false,
    this.isLoadingMore = false,
    this.error,
    this.currentPage = 1,
    this.lastPage = 1,
  });

  final List<Order> orders;
  final bool isLoading;
  final bool isLoadingMore;
  final String? error;
  final int currentPage;
  final int lastPage;

  bool get hasMore => currentPage < lastPage;

  OrdersState copyWith({
    List<Order>? orders,
    bool? isLoading,
    bool? isLoadingMore,
    String? error,
    int? currentPage,
    int? lastPage,
    bool clearError = false,
    bool clearOrders = false,
  }) {
    return OrdersState(
      orders: clearOrders ? const [] : (orders ?? this.orders),
      isLoading: isLoading ?? this.isLoading,
      isLoadingMore: isLoadingMore ?? this.isLoadingMore,
      error: clearError ? null : (error ?? this.error),
      currentPage: currentPage ?? this.currentPage,
      lastPage: lastPage ?? this.lastPage,
    );
  }
}

class OrdersNotifier extends StateNotifier<OrdersState> {
  OrdersNotifier(this._repository) : super(const OrdersState());

  final CommerceRepository _repository;

  Future<void> load() async {
    state = state.copyWith(isLoading: true, clearError: true, clearOrders: true);
    try {
      final page = await _repository.listOrders();
      state = state.copyWith(
        orders: page.orders,
        currentPage: page.currentPage,
        lastPage: page.lastPage,
        isLoading: false,
      );
    } on DioException catch (error) {
      state = state.copyWith(
        isLoading: false,
        error: error.response?.data?['message']?.toString() ?? error.message,
      );
    } catch (error) {
      state = state.copyWith(isLoading: false, error: error.toString());
    }
  }

  Future<void> loadMore() async {
    if (state.isLoadingMore || !state.hasMore) {
      return;
    }

    state = state.copyWith(isLoadingMore: true, clearError: true);
    try {
      final page = await _repository.listOrders(page: state.currentPage + 1);
      state = state.copyWith(
        orders: [...state.orders, ...page.orders],
        currentPage: page.currentPage,
        lastPage: page.lastPage,
        isLoadingMore: false,
      );
    } on DioException catch (error) {
      state = state.copyWith(
        isLoadingMore: false,
        error: error.response?.data?['message']?.toString() ?? error.message,
      );
    } catch (error) {
      state = state.copyWith(isLoadingMore: false, error: error.toString());
    }
  }
}

final ordersNotifierProvider =
    StateNotifierProvider<OrdersNotifier, OrdersState>((ref) {
  return OrdersNotifier(ref.watch(commerceRepositoryProvider));
});

final orderDetailProvider = FutureProvider.family<Order, String>((ref, id) async {
  return ref.watch(commerceRepositoryProvider).getOrder(id);
});

class CheckoutState {
  const CheckoutState({
    this.isSubmitting = false,
    this.error,
    this.lastOrder,
  });

  final bool isSubmitting;
  final String? error;
  final Order? lastOrder;

  CheckoutState copyWith({
    bool? isSubmitting,
    String? error,
    Order? lastOrder,
    bool clearError = false,
    bool clearOrder = false,
  }) {
    return CheckoutState(
      isSubmitting: isSubmitting ?? this.isSubmitting,
      error: clearError ? null : (error ?? this.error),
      lastOrder: clearOrder ? null : (lastOrder ?? this.lastOrder),
    );
  }
}

class CheckoutNotifier extends StateNotifier<CheckoutState> {
  CheckoutNotifier(this._repository) : super(const CheckoutState());

  final CommerceRepository _repository;

  Future<Order?> submit({
    required int cartVersion,
    required ShippingAddress address,
  }) async {
    state = state.copyWith(isSubmitting: true, clearError: true, clearOrder: true);
    try {
      final result = await _repository.checkout(
        cartVersion: cartVersion,
        shippingAddress: address,
      );
      state = state.copyWith(isSubmitting: false, lastOrder: result.order);
      return result.order;
    } catch (error) {
      state = state.copyWith(isSubmitting: false, error: describeFailure(error));
      return null;
    }
  }

  void reset() {
    state = const CheckoutState();
  }
}

final checkoutNotifierProvider =
    StateNotifierProvider<CheckoutNotifier, CheckoutState>((ref) {
  return CheckoutNotifier(ref.watch(commerceRepositoryProvider));
});
