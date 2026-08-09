import 'package:dio/dio.dart';
import 'package:livecommerce_mobile/features/wallet/domain/entities/wallet.dart';

class WalletRemoteDataSource {
  WalletRemoteDataSource(this._dio);

  final Dio _dio;

  Future<Wallet> fetchWallet() async {
    final response = await _dio.get<Map<String, dynamic>>('/wallet');
    return Wallet.fromJson(response.data!['data'] as Map<String, dynamic>);
  }

  Future<WalletTransactionPage> fetchTransactions({String? cursor}) async {
    final response = await _dio.get<Map<String, dynamic>>(
      '/wallet/transactions',
      queryParameters: {if (cursor != null) 'cursor': cursor},
    );

    final data = response.data!['data'] as List<dynamic>;
    final meta = response.data!['meta'] as Map<String, dynamic>? ?? const {};

    return WalletTransactionPage(
      items: data
          .map((item) =>
              WalletTransaction.fromJson(item as Map<String, dynamic>))
          .toList(),
      nextCursor: meta['next_cursor'] as String?,
      hasMore: meta['has_more'] as bool? ?? false,
    );
  }

  Future<TopUpIntent> startTopUp({
    required int amount,
    required String method,
    String? paymentMethodId,
  }) async {
    final response = await _dio.post<Map<String, dynamic>>(
      '/wallet/topups',
      data: {
        'amount': amount,
        'method': method,
        if (paymentMethodId != null) 'payment_method_id': paymentMethodId,
      },
    );
    return TopUpIntent.fromJson(response.data!['data'] as Map<String, dynamic>);
  }

  Future<BitcoinQuote> bitcoinQuote(int amount) async {
    final response = await _dio.get<Map<String, dynamic>>(
      '/wallet/bitcoin-quote',
      queryParameters: {'amount': amount},
    );
    return BitcoinQuote.fromJson(
      response.data!['data'] as Map<String, dynamic>,
    );
  }

  Future<List<PaymentCard>> fetchCards() async {
    final response = await _dio.get<Map<String, dynamic>>('/wallet/cards');
    final data = response.data!['data'] as List<dynamic>;
    return data
        .map((item) => PaymentCard.fromJson(item as Map<String, dynamic>))
        .toList();
  }

  Future<PaymentCard> storeCard({
    required String cardNumber,
    required String holderName,
    required int expMonth,
    required int expYear,
    bool isDefault = false,
  }) async {
    final response = await _dio.post<Map<String, dynamic>>(
      '/wallet/cards',
      data: {
        'card_number': cardNumber,
        'holder_name': holderName,
        'exp_month': expMonth,
        'exp_year': expYear,
        'is_default': isDefault,
      },
    );
    return PaymentCard.fromJson(response.data!['data'] as Map<String, dynamic>);
  }

  Future<void> deleteCard(String id) async {
    await _dio.delete<Map<String, dynamic>>('/wallet/cards/$id');
  }

  Future<WalletTransaction> confirmTopUp({
    required String transactionId,
    bool success = true,
  }) async {
    final response = await _dio.post<Map<String, dynamic>>(
      '/wallet/topups/$transactionId/confirm',
      data: {'success': success},
    );
    return WalletTransaction.fromJson(
      response.data!['data'] as Map<String, dynamic>,
    );
  }

  Future<List<WalletWithdrawal>> fetchWithdrawals() async {
    final response =
        await _dio.get<Map<String, dynamic>>('/wallet/withdrawals');
    final data = response.data!['data'] as List<dynamic>;
    return data
        .map((item) => WalletWithdrawal.fromJson(item as Map<String, dynamic>))
        .toList();
  }

  Future<WalletWithdrawal> requestWithdrawal({
    required int amount,
    required String method,
    required String cardNumber,
    required String cardHolder,
  }) async {
    final response = await _dio.post<Map<String, dynamic>>(
      '/wallet/withdrawals',
      data: {
        'amount': amount,
        'method': method,
        'card_number': cardNumber,
        'card_holder': cardHolder,
      },
    );
    return WalletWithdrawal.fromJson(
      response.data!['data'] as Map<String, dynamic>,
    );
  }

  Future<WalletWithdrawal> cancelWithdrawal(String id) async {
    final response = await _dio.post<Map<String, dynamic>>(
      '/wallet/withdrawals/$id/cancel',
    );
    return WalletWithdrawal.fromJson(
      response.data!['data'] as Map<String, dynamic>,
    );
  }
}

class WalletRepository {
  WalletRepository({required WalletRemoteDataSource remote}) : _remote = remote;

  final WalletRemoteDataSource _remote;

  Future<Wallet> getWallet() => _remote.fetchWallet();

  Future<WalletTransactionPage> listTransactions({String? cursor}) =>
      _remote.fetchTransactions(cursor: cursor);

  Future<TopUpIntent> startTopUp({
    required int amount,
    required String method,
    String? paymentMethodId,
  }) =>
      _remote.startTopUp(
        amount: amount,
        method: method,
        paymentMethodId: paymentMethodId,
      );

  Future<BitcoinQuote> bitcoinQuote(int amount) =>
      _remote.bitcoinQuote(amount);

  Future<List<PaymentCard>> listCards() => _remote.fetchCards();

  Future<PaymentCard> storeCard({
    required String cardNumber,
    required String holderName,
    required int expMonth,
    required int expYear,
    bool isDefault = false,
  }) =>
      _remote.storeCard(
        cardNumber: cardNumber,
        holderName: holderName,
        expMonth: expMonth,
        expYear: expYear,
        isDefault: isDefault,
      );

  Future<void> deleteCard(String id) => _remote.deleteCard(id);

  Future<WalletTransaction> confirmTopUp(String transactionId,
          {bool success = true}) =>
      _remote.confirmTopUp(transactionId: transactionId, success: success);

  /// Recent transactions are enough to follow a top-up we just started.
  Future<WalletTransaction?> findTransaction(String id) async {
    final page = await _remote.fetchTransactions();

    for (final transaction in page.items) {
      if (transaction.id == id) {
        return transaction;
      }
    }

    return null;
  }

  Future<List<WalletWithdrawal>> listWithdrawals() => _remote.fetchWithdrawals();

  Future<WalletWithdrawal> requestWithdrawal({
    required int amount,
    required String method,
    required String cardNumber,
    required String cardHolder,
  }) =>
      _remote.requestWithdrawal(
        amount: amount,
        method: method,
        cardNumber: cardNumber,
        cardHolder: cardHolder,
      );

  Future<WalletWithdrawal> cancelWithdrawal(String id) =>
      _remote.cancelWithdrawal(id);
}
