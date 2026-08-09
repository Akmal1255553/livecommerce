import 'package:livecommerce_mobile/core/network/api_json.dart';
import 'package:livecommerce_mobile/features/commerce/domain/entities/money_amount.dart';

class WalletLimits {
  const WalletLimits({
    required this.topUpMin,
    required this.topUpMax,
    required this.withdrawalMin,
    required this.withdrawalMax,
    required this.withdrawalFeePercent,
  });

  final int topUpMin;
  final int topUpMax;
  final int withdrawalMin;
  final int withdrawalMax;
  final double withdrawalFeePercent;

  factory WalletLimits.fromJson(Map<String, dynamic> json) {
    return WalletLimits(
      topUpMin: (json['top_up_min'] as num?)?.toInt() ?? 0,
      topUpMax: (json['top_up_max'] as num?)?.toInt() ?? 0,
      withdrawalMin: (json['withdrawal_min'] as num?)?.toInt() ?? 0,
      withdrawalMax: (json['withdrawal_max'] as num?)?.toInt() ?? 0,
      withdrawalFeePercent:
          (json['withdrawal_fee_percent'] as num?)?.toDouble() ?? 0,
    );
  }

  /// Fee the backend will add on top of [amount], mirrored client-side so the
  /// user sees the real debit before confirming.
  int feeFor(int amount) {
    if (withdrawalFeePercent <= 0) {
      return 0;
    }
    return (amount * withdrawalFeePercent / 100).ceil();
  }
}

class Wallet {
  const Wallet({
    required this.id,
    required this.currency,
    required this.available,
    required this.held,
    required this.total,
    required this.limits,
  });

  final String id;
  final String currency;
  final MoneyAmount available;
  final MoneyAmount held;
  final MoneyAmount total;
  final WalletLimits limits;

  factory Wallet.fromJson(Map<String, dynamic> json) {
    return Wallet(
      id: json['id'] as String,
      currency: json['currency'] as String? ?? 'UZS',
      available: MoneyAmount.fromJson(
        (json['available'] as Map).cast<String, dynamic>(),
      ),
      held: MoneyAmount.fromJson((json['held'] as Map).cast<String, dynamic>()),
      total: MoneyAmount.fromJson(
        (json['total'] as Map).cast<String, dynamic>(),
      ),
      limits: WalletLimits.fromJson(
        (json['limits'] as Map?)?.cast<String, dynamic>() ?? const {},
      ),
    );
  }
}

enum WalletTransactionType {
  topUp,
  withdrawal,
  orderPayment,
  refund,
  payout,
  adjustment;

  static WalletTransactionType parse(String? raw) {
    return switch (raw) {
      'topup' => WalletTransactionType.topUp,
      'withdrawal' => WalletTransactionType.withdrawal,
      'order_payment' => WalletTransactionType.orderPayment,
      'refund' => WalletTransactionType.refund,
      'payout' => WalletTransactionType.payout,
      _ => WalletTransactionType.adjustment,
    };
  }
}

enum WalletTransactionStatus {
  pending,
  completed,
  failed,
  cancelled;

  static WalletTransactionStatus parse(String? raw) {
    return switch (raw) {
      'completed' => WalletTransactionStatus.completed,
      'failed' => WalletTransactionStatus.failed,
      'cancelled' => WalletTransactionStatus.cancelled,
      _ => WalletTransactionStatus.pending,
    };
  }
}

class WalletTransaction {
  const WalletTransaction({
    required this.id,
    required this.type,
    required this.status,
    required this.isCredit,
    required this.amount,
    this.balanceAfter,
    this.method,
    this.description,
    this.createdAt,
  });

  final String id;
  final WalletTransactionType type;
  final WalletTransactionStatus status;
  final bool isCredit;
  final MoneyAmount amount;
  final MoneyAmount? balanceAfter;
  final String? method;
  final String? description;
  final String? createdAt;

  factory WalletTransaction.fromJson(Map<String, dynamic> json) {
    final balance = json['balance_after'];
    return WalletTransaction(
      id: json['id'] as String,
      type: WalletTransactionType.parse(json['type'] as String?),
      status: WalletTransactionStatus.parse(json['status'] as String?),
      isCredit: json['direction'] == 'credit',
      amount: MoneyAmount.fromJson(
        (json['amount'] as Map).cast<String, dynamic>(),
      ),
      balanceAfter: balance is Map
          ? MoneyAmount.fromJson(balance.cast<String, dynamic>())
          : null,
      method: json['method'] as String?,
      description: json['description'] as String?,
      createdAt: json['created_at'] as String?,
    );
  }
}

class WalletTransactionPage {
  const WalletTransactionPage({
    required this.items,
    this.nextCursor,
    this.hasMore = false,
  });

  final List<WalletTransaction> items;
  final String? nextCursor;
  final bool hasMore;
}

class TopUpIntent {
  const TopUpIntent({
    required this.transaction,
    this.paymentUrl,
    this.cryptoAddress,
    this.cryptoAmount,
    this.cryptoCurrency,
    this.exchangeRate,
    this.expiresAt,
    this.qrPayload,
  });

  final WalletTransaction transaction;
  final String? paymentUrl;
  final String? cryptoAddress;
  final String? cryptoAmount;
  final String? cryptoCurrency;
  final double? exchangeRate;
  final String? expiresAt;
  final String? qrPayload;

  bool get isBitcoin =>
      cryptoAddress != null &&
      cryptoAddress!.isNotEmpty &&
      (qrPayload != null || cryptoAmount != null);

  factory TopUpIntent.fromJson(Map<String, dynamic> json) {
    return TopUpIntent(
      transaction: WalletTransaction.fromJson(
        unwrapApiResource(json['transaction']),
      ),
      paymentUrl: json['payment_url'] as String?,
      cryptoAddress: json['crypto_address'] as String?,
      cryptoAmount: json['crypto_amount'] as String?,
      cryptoCurrency: json['crypto_currency'] as String?,
      exchangeRate: (json['exchange_rate'] as num?)?.toDouble(),
      expiresAt: json['expires_at'] as String?,
      qrPayload: json['qr_payload'] as String?,
    );
  }
}

class BitcoinQuote {
  const BitcoinQuote({
    required this.amount,
    required this.cryptoAmount,
    required this.cryptoCurrency,
    required this.exchangeRate,
  });

  final int amount;
  final String cryptoAmount;
  final String cryptoCurrency;
  final double exchangeRate;

  factory BitcoinQuote.fromJson(Map<String, dynamic> json) {
    return BitcoinQuote(
      amount: (json['amount'] as num?)?.toInt() ?? 0,
      cryptoAmount: json['crypto_amount'] as String? ?? '0',
      cryptoCurrency: json['crypto_currency'] as String? ?? 'BTC',
      exchangeRate: (json['exchange_rate'] as num?)?.toDouble() ?? 0,
    );
  }
}

class PaymentCard {
  const PaymentCard({
    required this.id,
    required this.last4,
    required this.brand,
    required this.holderName,
    required this.expMonth,
    required this.expYear,
    required this.isDefault,
  });

  final String id;
  final String last4;
  final String brand;
  final String holderName;
  final int expMonth;
  final int expYear;
  final bool isDefault;

  factory PaymentCard.fromJson(Map<String, dynamic> json) {
    return PaymentCard(
      id: json['id'] as String,
      last4: json['last4'] as String? ?? '',
      brand: json['brand'] as String? ?? 'unknown',
      holderName: json['holder_name'] as String? ?? '',
      expMonth: (json['exp_month'] as num?)?.toInt() ?? 0,
      expYear: (json['exp_year'] as num?)?.toInt() ?? 0,
      isDefault: json['is_default'] as bool? ?? false,
    );
  }
}

enum WithdrawalStatus {
  requested,
  processing,
  completed,
  rejected,
  cancelled;

  static WithdrawalStatus parse(String? raw) {
    return switch (raw) {
      'processing' => WithdrawalStatus.processing,
      'completed' => WithdrawalStatus.completed,
      'rejected' => WithdrawalStatus.rejected,
      'cancelled' => WithdrawalStatus.cancelled,
      _ => WithdrawalStatus.requested,
    };
  }

  bool get isPending =>
      this == WithdrawalStatus.requested || this == WithdrawalStatus.processing;
}

class WalletWithdrawal {
  const WalletWithdrawal({
    required this.id,
    required this.status,
    required this.amount,
    required this.fee,
    required this.total,
    required this.cardLast4,
    required this.cardHolder,
    this.method,
    this.rejectionReason,
    this.createdAt,
    this.processedAt,
  });

  final String id;
  final WithdrawalStatus status;
  final MoneyAmount amount;
  final MoneyAmount fee;
  final MoneyAmount total;
  final String cardLast4;
  final String cardHolder;
  final String? method;
  final String? rejectionReason;
  final String? createdAt;
  final String? processedAt;

  factory WalletWithdrawal.fromJson(Map<String, dynamic> json) {
    return WalletWithdrawal(
      id: json['id'] as String,
      status: WithdrawalStatus.parse(json['status'] as String?),
      amount: MoneyAmount.fromJson(
        (json['amount'] as Map).cast<String, dynamic>(),
      ),
      fee: MoneyAmount.fromJson((json['fee'] as Map).cast<String, dynamic>()),
      total: MoneyAmount.fromJson(
        (json['total'] as Map).cast<String, dynamic>(),
      ),
      cardLast4: json['card_last4'] as String? ?? '',
      cardHolder: json['card_holder'] as String? ?? '',
      method: json['method'] as String?,
      rejectionReason: json['rejection_reason'] as String?,
      createdAt: json['created_at'] as String?,
      processedAt: json['processed_at'] as String?,
    );
  }
}
