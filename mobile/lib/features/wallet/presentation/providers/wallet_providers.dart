import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:livecommerce_mobile/core/errors/error_handler.dart';
import 'package:livecommerce_mobile/features/auth/presentation/providers/auth_providers.dart';
import 'package:livecommerce_mobile/features/wallet/data/wallet_repository.dart';
import 'package:livecommerce_mobile/features/wallet/domain/entities/wallet.dart';

final walletRepositoryProvider = Provider<WalletRepository>((ref) {
  ref.watch(authRepositoryProvider);
  final dio = ref.watch(authDioProvider);
  return WalletRepository(remote: WalletRemoteDataSource(dio));
});

class WalletState {
  const WalletState({
    this.wallet,
    this.transactions = const [],
    this.withdrawals = const [],
    this.nextCursor,
    this.hasMore = false,
    this.isLoading = false,
    this.isLoadingMore = false,
    this.isSubmitting = false,
    this.error,
  });

  final Wallet? wallet;
  final List<WalletTransaction> transactions;
  final List<WalletWithdrawal> withdrawals;
  final String? nextCursor;
  final bool hasMore;
  final bool isLoading;
  final bool isLoadingMore;
  final bool isSubmitting;
  final String? error;

  List<WalletWithdrawal> get pendingWithdrawals =>
      withdrawals.where((item) => item.status.isPending).toList();

  WalletState copyWith({
    Wallet? wallet,
    List<WalletTransaction>? transactions,
    List<WalletWithdrawal>? withdrawals,
    String? nextCursor,
    bool? hasMore,
    bool? isLoading,
    bool? isLoadingMore,
    bool? isSubmitting,
    String? error,
    bool clearError = false,
    bool clearCursor = false,
  }) {
    return WalletState(
      wallet: wallet ?? this.wallet,
      transactions: transactions ?? this.transactions,
      withdrawals: withdrawals ?? this.withdrawals,
      nextCursor: clearCursor ? null : (nextCursor ?? this.nextCursor),
      hasMore: hasMore ?? this.hasMore,
      isLoading: isLoading ?? this.isLoading,
      isLoadingMore: isLoadingMore ?? this.isLoadingMore,
      isSubmitting: isSubmitting ?? this.isSubmitting,
      error: clearError ? null : (error ?? this.error),
    );
  }
}

class WalletNotifier extends StateNotifier<WalletState> {
  WalletNotifier(this._repository) : super(const WalletState());

  final WalletRepository _repository;

  Future<void> load() async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final wallet = await _repository.getWallet();
      final page = await _repository.listTransactions();
      final withdrawals = await _repository.listWithdrawals();

      state = state.copyWith(
        wallet: wallet,
        transactions: page.items,
        withdrawals: withdrawals,
        nextCursor: page.nextCursor,
        hasMore: page.hasMore,
        isLoading: false,
        clearCursor: page.nextCursor == null,
      );
    } catch (error) {
      state = state.copyWith(isLoading: false, error: describeFailure(error));
    }
  }

  Future<void> loadMore() async {
    if (state.isLoadingMore || !state.hasMore || state.nextCursor == null) {
      return;
    }

    state = state.copyWith(isLoadingMore: true);
    try {
      final page = await _repository.listTransactions(cursor: state.nextCursor);
      state = state.copyWith(
        transactions: [...state.transactions, ...page.items],
        nextCursor: page.nextCursor,
        hasMore: page.hasMore,
        isLoadingMore: false,
        clearCursor: page.nextCursor == null,
      );
    } catch (error) {
      state = state.copyWith(
        isLoadingMore: false,
        error: describeFailure(error),
      );
    }
  }

  /// Starts a top-up and, in sandbox, settles it right away so the balance
  /// reflects the payment without a provider callback.
  Future<bool> topUp({required int amount, required String method}) async {
    state = state.copyWith(isSubmitting: true, clearError: true);
    try {
      final intent = await _repository.startTopUp(amount: amount, method: method);
      await _repository.confirmTopUp(intent.transaction.id);
      state = state.copyWith(isSubmitting: false);
      await load();
      return true;
    } catch (error) {
      state = state.copyWith(
        isSubmitting: false,
        error: describeFailure(error),
      );
      return false;
    }
  }

  Future<bool> withdraw({
    required int amount,
    required String method,
    required String cardNumber,
    required String cardHolder,
  }) async {
    state = state.copyWith(isSubmitting: true, clearError: true);
    try {
      await _repository.requestWithdrawal(
        amount: amount,
        method: method,
        cardNumber: cardNumber,
        cardHolder: cardHolder,
      );
      state = state.copyWith(isSubmitting: false);
      await load();
      return true;
    } catch (error) {
      state = state.copyWith(
        isSubmitting: false,
        error: describeFailure(error),
      );
      return false;
    }
  }

  Future<bool> cancelWithdrawal(String id) async {
    state = state.copyWith(isSubmitting: true, clearError: true);
    try {
      await _repository.cancelWithdrawal(id);
      state = state.copyWith(isSubmitting: false);
      await load();
      return true;
    } catch (error) {
      state = state.copyWith(
        isSubmitting: false,
        error: describeFailure(error),
      );
      return false;
    }
  }

  void clearError() {
    state = state.copyWith(clearError: true);
  }
}

final walletNotifierProvider =
    StateNotifierProvider<WalletNotifier, WalletState>((ref) {
  return WalletNotifier(ref.watch(walletRepositoryProvider));
});
