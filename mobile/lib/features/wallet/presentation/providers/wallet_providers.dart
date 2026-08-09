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
    this.cards = const [],
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
  final List<PaymentCard> cards;
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
    List<PaymentCard>? cards,
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
      cards: cards ?? this.cards,
      nextCursor: clearCursor ? null : (nextCursor ?? this.nextCursor),
      hasMore: hasMore ?? this.hasMore,
      isLoading: isLoading ?? this.isLoading,
      isLoadingMore: isLoadingMore ?? this.isLoadingMore,
      isSubmitting: isSubmitting ?? this.isSubmitting,
      error: clearError ? null : (error ?? this.error),
    );
  }
}

enum TopUpOutcome { credited, awaitingProvider, awaitingBitcoin, failed }

class TopUpStart {
  const TopUpStart(
    this.outcome, {
    this.transactionId,
    this.paymentUrl,
    this.cryptoAddress,
    this.cryptoAmount,
    this.cryptoCurrency,
    this.expiresAt,
    this.qrPayload,
    this.sandboxConfirm = false,
  });

  final TopUpOutcome outcome;
  final String? transactionId;
  final String? paymentUrl;
  final String? cryptoAddress;
  final String? cryptoAmount;
  final String? cryptoCurrency;
  final String? expiresAt;
  final String? qrPayload;
  final bool sandboxConfirm;
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
      final cards = await _repository.listCards();

      state = state.copyWith(
        wallet: wallet,
        transactions: page.items,
        withdrawals: withdrawals,
        cards: cards,
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

  Future<BitcoinQuote?> quoteBitcoin(int amount) async {
    try {
      return await _repository.bitcoinQuote(amount);
    } catch (_) {
      return null;
    }
  }

  Future<PaymentCard?> addCard({
    required String cardNumber,
    required String holderName,
    required int expMonth,
    required int expYear,
  }) async {
    state = state.copyWith(isSubmitting: true, clearError: true);
    try {
      final card = await _repository.storeCard(
        cardNumber: cardNumber,
        holderName: holderName,
        expMonth: expMonth,
        expYear: expYear,
        isDefault: state.cards.isEmpty,
      );
      state = state.copyWith(
        isSubmitting: false,
        cards: [card, ...state.cards.where((c) => c.id != card.id)],
      );
      return card;
    } catch (error) {
      state = state.copyWith(
        isSubmitting: false,
        error: describeFailure(error),
      );
      return null;
    }
  }

  /// Starts a top-up.
  ///
  /// Bitcoin returns invoice details for the QR dialog. Card / sandbox confirm
  /// URLs settle immediately. Other provider URLs open externally.
  Future<TopUpStart> topUp({
    required int amount,
    required String method,
    String? paymentMethodId,
  }) async {
    state = state.copyWith(isSubmitting: true, clearError: true);
    try {
      final intent = await _repository.startTopUp(
        amount: amount,
        method: method,
        paymentMethodId: paymentMethodId,
      );
      final paymentUrl = intent.paymentUrl;

      if (intent.isBitcoin) {
        state = state.copyWith(isSubmitting: false);
        return TopUpStart(
          TopUpOutcome.awaitingBitcoin,
          transactionId: intent.transaction.id,
          paymentUrl: paymentUrl,
          cryptoAddress: intent.cryptoAddress,
          cryptoAmount: intent.cryptoAmount,
          cryptoCurrency: intent.cryptoCurrency,
          expiresAt: intent.expiresAt,
          qrPayload: intent.qrPayload,
          sandboxConfirm:
              paymentUrl != null && paymentUrl.contains('/wallet/topups/'),
        );
      }

      if (paymentUrl == null || paymentUrl.contains('/wallet/topups/')) {
        await _repository.confirmTopUp(intent.transaction.id);
        state = state.copyWith(isSubmitting: false);
        await load();
        return const TopUpStart(TopUpOutcome.credited);
      }

      state = state.copyWith(isSubmitting: false);
      return TopUpStart(
        TopUpOutcome.awaitingProvider,
        transactionId: intent.transaction.id,
        paymentUrl: paymentUrl,
      );
    } catch (error) {
      state = state.copyWith(
        isSubmitting: false,
        error: describeFailure(error),
      );
      return const TopUpStart(TopUpOutcome.failed);
    }
  }

  Future<TopUpOutcome> confirmSandboxTopUp(String transactionId) async {
    state = state.copyWith(isSubmitting: true, clearError: true);
    try {
      await _repository.confirmTopUp(transactionId);
      state = state.copyWith(isSubmitting: false);
      await load();
      return TopUpOutcome.credited;
    } catch (error) {
      state = state.copyWith(
        isSubmitting: false,
        error: describeFailure(error),
      );
      return TopUpOutcome.failed;
    }
  }

  /// Waits for the provider callback to settle a top-up we already started.
  Future<TopUpOutcome> awaitTopUp(
    String transactionId, {
    Duration interval = const Duration(seconds: 3),
    int attempts = 40,
  }) async {
    for (var attempt = 0; attempt < attempts; attempt++) {
      await Future<void>.delayed(interval);

      if (!mounted) {
        return TopUpOutcome.awaitingProvider;
      }

      try {
        final transaction = await _repository.findTransaction(transactionId);

        switch (transaction?.status) {
          case WalletTransactionStatus.completed:
            await load();
            return TopUpOutcome.credited;
          case WalletTransactionStatus.failed:
          case WalletTransactionStatus.cancelled:
            await load();
            return TopUpOutcome.failed;
          case WalletTransactionStatus.pending:
          case null:
            break;
        }
      } catch (_) {
        // A flaky poll should not end the wait; the callback may still arrive.
      }
    }

    await load();
    return TopUpOutcome.awaitingProvider;
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
