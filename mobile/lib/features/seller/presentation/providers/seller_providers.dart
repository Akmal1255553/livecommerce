import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:livecommerce_mobile/core/errors/error_handler.dart';
import 'package:livecommerce_mobile/features/auth/presentation/providers/auth_providers.dart';
import 'package:livecommerce_mobile/features/commerce/domain/entities/order.dart';
import 'package:livecommerce_mobile/features/seller/data/seller_repository.dart';
import 'package:livecommerce_mobile/features/seller/domain/entities/seller_models.dart';

final sellerRepositoryProvider = Provider<SellerRepository>((ref) {
  ref.watch(authRepositoryProvider);
  final dio = ref.watch(authDioProvider);
  return SellerRepository(remote: SellerRemoteDataSource(dio));
});

class SellerDashboardState {
  const SellerDashboardState({
    this.dashboard,
    this.isLoading = false,
    this.error,
  });

  final SellerDashboard? dashboard;
  final bool isLoading;
  final String? error;

  SellerDashboardState copyWith({
    SellerDashboard? dashboard,
    bool? isLoading,
    String? error,
    bool clearError = false,
  }) {
    return SellerDashboardState(
      dashboard: dashboard ?? this.dashboard,
      isLoading: isLoading ?? this.isLoading,
      error: clearError ? null : (error ?? this.error),
    );
  }
}

class SellerDashboardNotifier extends StateNotifier<SellerDashboardState> {
  SellerDashboardNotifier(this._repository) : super(const SellerDashboardState());

  final SellerRepository _repository;

  Future<void> load() async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final dashboard = await _repository.dashboard();
      state = state.copyWith(dashboard: dashboard, isLoading: false);
    } catch (error) {
      state = state.copyWith(isLoading: false, error: describeFailure(error));
    }
  }
}

final sellerDashboardProvider =
    StateNotifierProvider<SellerDashboardNotifier, SellerDashboardState>((ref) {
  return SellerDashboardNotifier(ref.watch(sellerRepositoryProvider));
});

final sellerCategoriesProvider = FutureProvider<List<SellerCategory>>((ref) {
  return ref.watch(sellerRepositoryProvider).categories();
});

class SellerProductsState {
  const SellerProductsState({
    this.products = const [],
    this.isLoading = false,
    this.error,
  });

  final List<SellerProductSummary> products;
  final bool isLoading;
  final String? error;

  SellerProductsState copyWith({
    List<SellerProductSummary>? products,
    bool? isLoading,
    String? error,
    bool clearError = false,
  }) {
    return SellerProductsState(
      products: products ?? this.products,
      isLoading: isLoading ?? this.isLoading,
      error: clearError ? null : (error ?? this.error),
    );
  }
}

class SellerProductsNotifier extends StateNotifier<SellerProductsState> {
  SellerProductsNotifier(this._repository) : super(const SellerProductsState());

  final SellerRepository _repository;

  Future<void> load() async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final products = await _repository.myProducts();
      state = state.copyWith(products: products, isLoading: false);
    } catch (error) {
      state = state.copyWith(isLoading: false, error: describeFailure(error));
    }
  }

  Future<bool> create({
    required String title,
    String? description,
    required int categoryId,
    required double price,
    required int stockQuantity,
    String? sku,
    String? imageUrl,
  }) async {
    try {
      await _repository.createProduct(
        title: title,
        description: description,
        categoryId: categoryId,
        price: price,
        stockQuantity: stockQuantity,
        sku: sku,
        imageUrl: imageUrl,
      );
      await load();
      return true;
    } catch (error) {
      state = state.copyWith(error: describeFailure(error));
      return false;
    }
  }

  Future<bool> remove(String id) async {
    try {
      await _repository.deleteProduct(id);
      await load();
      return true;
    } catch (error) {
      state = state.copyWith(error: describeFailure(error));
      return false;
    }
  }
}

final sellerProductsProvider =
    StateNotifierProvider<SellerProductsNotifier, SellerProductsState>((ref) {
  return SellerProductsNotifier(ref.watch(sellerRepositoryProvider));
});

class SellerOrdersState {
  const SellerOrdersState({
    this.orders = const [],
    this.isLoading = false,
    this.error,
  });

  final List<SellerOrderSummary> orders;
  final bool isLoading;
  final String? error;

  SellerOrdersState copyWith({
    List<SellerOrderSummary>? orders,
    bool? isLoading,
    String? error,
    bool clearError = false,
  }) {
    return SellerOrdersState(
      orders: orders ?? this.orders,
      isLoading: isLoading ?? this.isLoading,
      error: clearError ? null : (error ?? this.error),
    );
  }
}

class SellerOrdersNotifier extends StateNotifier<SellerOrdersState> {
  SellerOrdersNotifier(this._repository) : super(const SellerOrdersState());

  final SellerRepository _repository;

  Future<void> load() async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final orders = await _repository.sellerOrders();
      state = state.copyWith(orders: orders, isLoading: false);
    } catch (error) {
      state = state.copyWith(isLoading: false, error: describeFailure(error));
    }
  }
}

final sellerOrdersProvider =
    StateNotifierProvider<SellerOrdersNotifier, SellerOrdersState>((ref) {
  return SellerOrdersNotifier(ref.watch(sellerRepositoryProvider));
});

class SellerOrderDetailState {
  const SellerOrderDetailState({
    this.order,
    this.isLoading = false,
    this.isUpdating = false,
    this.error,
  });

  final Order? order;
  final bool isLoading;
  final bool isUpdating;
  final String? error;

  SellerOrderDetailState copyWith({
    Order? order,
    bool? isLoading,
    bool? isUpdating,
    String? error,
    bool clearError = false,
  }) {
    return SellerOrderDetailState(
      order: order ?? this.order,
      isLoading: isLoading ?? this.isLoading,
      isUpdating: isUpdating ?? this.isUpdating,
      error: clearError ? null : (error ?? this.error),
    );
  }
}

class SellerOrderDetailNotifier extends StateNotifier<SellerOrderDetailState> {
  SellerOrderDetailNotifier(this._repository, this._orderId)
      : super(const SellerOrderDetailState());

  final SellerRepository _repository;
  final String _orderId;

  Future<void> load() async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final order = await _repository.sellerOrder(_orderId);
      state = state.copyWith(order: order, isLoading: false);
    } catch (error) {
      state = state.copyWith(isLoading: false, error: describeFailure(error));
    }
  }

  Future<bool> updateStatus(String status) async {
    final current = state.order;
    if (current == null) {
      return false;
    }

    state = state.copyWith(isUpdating: true, clearError: true);
    try {
      final order = await _repository.updateOrderStatus(
        orderId: _orderId,
        status: status,
        version: current.version,
      );
      state = state.copyWith(order: order, isUpdating: false);
      return true;
    } catch (error) {
      state = state.copyWith(isUpdating: false, error: describeFailure(error));
      return false;
    }
  }
}

final sellerOrderDetailProvider = StateNotifierProvider.family<
    SellerOrderDetailNotifier, SellerOrderDetailState, String>((ref, id) {
  return SellerOrderDetailNotifier(ref.watch(sellerRepositoryProvider), id);
});

class PublicStorePage {
  const PublicStorePage({
    required this.store,
    required this.products,
  });

  final SellerStore store;
  final List<SellerProductSummary> products;
}

final publicStoreProvider =
    FutureProvider.family<PublicStorePage, String>((ref, slug) async {
  final repo = ref.watch(sellerRepositoryProvider);
  final store = await repo.storeBySlug(slug);
  final products = await repo.storeProducts(slug);
  return PublicStorePage(store: store, products: products);
});
