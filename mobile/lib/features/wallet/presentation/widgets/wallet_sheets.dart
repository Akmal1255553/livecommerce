import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:livecommerce_mobile/core/l10n/app_localizations.dart';
import 'package:livecommerce_mobile/core/theme/app_colors.dart';
import 'package:livecommerce_mobile/core/theme/app_dimens.dart';
import 'package:livecommerce_mobile/core/utils/number_format.dart';
import 'package:livecommerce_mobile/features/wallet/domain/entities/wallet.dart';
import 'package:livecommerce_mobile/features/wallet/presentation/providers/wallet_providers.dart';
import 'package:livecommerce_mobile/shared/widgets/gradient_button.dart';
import 'package:qr_flutter/qr_flutter.dart';

Future<TopUpStart?> showWalletTopUpSheet(BuildContext context, Wallet wallet) {
  return showModalBottomSheet<TopUpStart>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    shape: const RoundedRectangleBorder(borderRadius: AppRadius.sheet),
    builder: (_) => _TopUpSheet(wallet: wallet),
  );
}

Future<bool?> showWalletWithdrawSheet(BuildContext context, Wallet wallet) {
  return showModalBottomSheet<bool>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    shape: const RoundedRectangleBorder(borderRadius: AppRadius.sheet),
    builder: (_) => _WithdrawSheet(wallet: wallet),
  );
}

Future<TopUpOutcome?> showBitcoinPaymentDialog(
  BuildContext context,
  TopUpStart start,
) {
  return showDialog<TopUpOutcome>(
    context: context,
    barrierDismissible: false,
    builder: (_) => _BitcoinPaymentDialog(start: start),
  );
}

/// Presets keep the common amounts one tap away instead of typing six zeros.
const _topUpPresets = <int>[50000, 100000, 250000, 500000, 1000000];

class _SheetShell extends StatelessWidget {
  const _SheetShell({required this.title, required this.children});

  final String title;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    final palette = AppPalette.of(context);

    return Padding(
      padding: EdgeInsets.only(
        bottom: MediaQuery.viewInsetsOf(context).bottom,
      ),
      child: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(
          AppSpacing.lg,
          AppSpacing.md,
          AppSpacing.lg,
          AppSpacing.xl,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Center(
              child: Container(
                width: 40,
                height: 4,
                decoration: BoxDecoration(
                  color: palette.outline,
                  borderRadius: AppRadius.pillAll,
                ),
              ),
            ),
            const SizedBox(height: AppSpacing.lg),
            Text(
              title,
              style: Theme.of(context)
                  .textTheme
                  .titleLarge
                  ?.copyWith(fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: AppSpacing.lg),
            ...children,
          ],
        ),
      ),
    );
  }
}

class _AmountField extends StatelessWidget {
  const _AmountField({
    required this.controller,
    required this.currency,
    this.autofocus = false,
  });

  final TextEditingController controller;
  final String currency;
  final bool autofocus;

  @override
  Widget build(BuildContext context) {
    final palette = AppPalette.of(context);

    return TextField(
      controller: controller,
      autofocus: autofocus,
      keyboardType: TextInputType.number,
      inputFormatters: [FilteringTextInputFormatter.digitsOnly],
      style: Theme.of(context).textTheme.headlineSmall?.copyWith(
            fontWeight: FontWeight.w800,
          ),
      decoration: InputDecoration(
        labelText: AppLocalizations.of(context)!.walletAmountLabel,
        hintText: '0',
        suffixText: currency,
        suffixStyle: TextStyle(
          color: palette.textTertiary,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }
}

class _LimitsHint extends StatelessWidget {
  const _LimitsHint({
    required this.min,
    required this.max,
    required this.currency,
  });

  final int min;
  final int max;
  final String currency;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final palette = AppPalette.of(context);

    return Text(
      '${l10n.walletMinAmount(formatPrice(min, currency))} · '
      '${l10n.walletMaxAmount(formatPrice(max, currency))}',
      style: Theme.of(context)
          .textTheme
          .bodySmall
          ?.copyWith(color: palette.textTertiary),
    );
  }
}

class _TopUpSheet extends ConsumerStatefulWidget {
  const _TopUpSheet({required this.wallet});

  final Wallet wallet;

  @override
  ConsumerState<_TopUpSheet> createState() => _TopUpSheetState();
}

class _TopUpSheetState extends ConsumerState<_TopUpSheet> {
  final _amountController = TextEditingController();
  String _method = 'bitcoin';
  String? _selectedCardId;
  BitcoinQuote? _quote;
  Timer? _quoteDebounce;
  bool _showAddCard = false;

  final _cardController = TextEditingController();
  final _holderController = TextEditingController();
  final _expMonthController = TextEditingController();
  final _expYearController = TextEditingController();

  @override
  void initState() {
    super.initState();
    _amountController.addListener(_onAmountChanged);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final cards = ref.read(walletNotifierProvider).cards;
      if (cards.isNotEmpty) {
        setState(() {
          _selectedCardId =
              cards.firstWhere((c) => c.isDefault, orElse: () => cards.first).id;
        });
      }
    });
  }

  @override
  void dispose() {
    _quoteDebounce?.cancel();
    _amountController.dispose();
    _cardController.dispose();
    _holderController.dispose();
    _expMonthController.dispose();
    _expYearController.dispose();
    super.dispose();
  }

  void _onAmountChanged() {
    setState(() {});
    _quoteDebounce?.cancel();
    if (_method != 'bitcoin' || !_isValid) {
      setState(() => _quote = null);
      return;
    }
    _quoteDebounce = Timer(const Duration(milliseconds: 350), () async {
      final quote =
          await ref.read(walletNotifierProvider.notifier).quoteBitcoin(_amount);
      if (mounted && _method == 'bitcoin') {
        setState(() => _quote = quote);
      }
    });
  }

  int get _amount => int.tryParse(_amountController.text.trim()) ?? 0;

  bool get _isValid =>
      _amount >= widget.wallet.limits.topUpMin &&
      _amount <= widget.wallet.limits.topUpMax;

  bool get _canSubmit {
    if (!_isValid) return false;
    if (_method == 'card') {
      return _selectedCardId != null && !_showAddCard;
    }
    return true;
  }

  Future<void> _submit() async {
    final result = await ref.read(walletNotifierProvider.notifier).topUp(
          amount: _amount,
          method: _method,
          paymentMethodId: _method == 'card' ? _selectedCardId : null,
        );

    if (result.outcome != TopUpOutcome.failed && mounted) {
      Navigator.of(context).pop(result);
    }
  }

  Future<void> _saveCard() async {
    final digits = _cardController.text.replaceAll(RegExp(r'\D'), '');
    final month = int.tryParse(_expMonthController.text.trim()) ?? 0;
    final year = int.tryParse(_expYearController.text.trim()) ?? 0;
    if (digits.length < 12 ||
        _holderController.text.trim().length < 3 ||
        month < 1 ||
        month > 12 ||
        year < DateTime.now().year) {
      return;
    }

    final card = await ref.read(walletNotifierProvider.notifier).addCard(
          cardNumber: digits,
          holderName: _holderController.text.trim(),
          expMonth: month,
          expYear: year,
        );

    if (card != null && mounted) {
      setState(() {
        _selectedCardId = card.id;
        _showAddCard = false;
        _cardController.clear();
        _holderController.clear();
        _expMonthController.clear();
        _expYearController.clear();
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final palette = AppPalette.of(context);
    final state = ref.watch(walletNotifierProvider);
    final limits = widget.wallet.limits;
    final cards = state.cards;

    return _SheetShell(
      title: l10n.walletTopUpTitle,
      children: [
        _AmountField(
          controller: _amountController,
          currency: widget.wallet.currency,
          autofocus: true,
        ),
        const SizedBox(height: AppSpacing.sm),
        _LimitsHint(
          min: limits.topUpMin,
          max: limits.topUpMax,
          currency: widget.wallet.currency,
        ),
        const SizedBox(height: AppSpacing.lg),
        Wrap(
          spacing: AppSpacing.sm,
          runSpacing: AppSpacing.sm,
          children: [
            for (final preset in _topUpPresets)
              _PresetChip(
                label: formatPrice(preset, widget.wallet.currency),
                selected: _amount == preset,
                onTap: () {
                  _amountController.text = '$preset';
                  _onAmountChanged();
                },
              ),
          ],
        ),
        const SizedBox(height: AppSpacing.xl),
        Text(
          l10n.walletMethodLabel,
          style: Theme.of(context).textTheme.labelLarge,
        ),
        const SizedBox(height: AppSpacing.sm),
        Row(
          children: [
            Expanded(
              child: _MethodTile(
                icon: Icons.currency_bitcoin,
                label: l10n.walletMethodBitcoin,
                selected: _method == 'bitcoin',
                onTap: () => setState(() {
                  _method = 'bitcoin';
                  _onAmountChanged();
                }),
              ),
            ),
            const SizedBox(width: AppSpacing.sm),
            Expanded(
              child: _MethodTile(
                icon: Icons.credit_card_rounded,
                label: l10n.walletMethodCard,
                selected: _method == 'card',
                onTap: () => setState(() {
                  _method = 'card';
                  _quote = null;
                }),
              ),
            ),
          ],
        ),
        if (_method == 'bitcoin' && _quote != null && _isValid) ...[
          const SizedBox(height: AppSpacing.lg),
          Container(
            padding: const EdgeInsets.all(AppSpacing.md),
            decoration: BoxDecoration(
              color: palette.surfaceHigh,
              borderRadius: AppRadius.smAll,
            ),
            child: Text(
              l10n.walletBitcoinApprox(
                '${_quote!.cryptoAmount} ${_quote!.cryptoCurrency}',
              ),
              style: Theme.of(context).textTheme.titleSmall?.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
            ),
          ),
        ],
        if (_method == 'card') ...[
          const SizedBox(height: AppSpacing.lg),
          if (_isValid)
            Container(
              padding: const EdgeInsets.all(AppSpacing.md),
              decoration: BoxDecoration(
                color: palette.surfaceHigh,
                borderRadius: AppRadius.smAll,
              ),
              child: Text(
                _selectedCardId == null
                    ? l10n.walletChargeAmount(
                        formatPrice(_amount, widget.wallet.currency),
                      )
                    : l10n.walletChargeFromCard(
                        cards
                            .firstWhere(
                              (c) => c.id == _selectedCardId,
                              orElse: () => cards.first,
                            )
                            .last4,
                        formatPrice(_amount, widget.wallet.currency),
                      ),
                style: Theme.of(context).textTheme.titleSmall?.copyWith(
                      fontWeight: FontWeight.w700,
                    ),
              ),
            ),
          const SizedBox(height: AppSpacing.md),
          if (!_showAddCard) ...[
            for (final card in cards)
              ListTile(
                contentPadding: EdgeInsets.zero,
                leading: Icon(
                  Icons.credit_card_rounded,
                  color: _selectedCardId == card.id
                      ? palette.brand
                      : palette.textSecondary,
                ),
                title: Text(l10n.walletCardMask(card.last4)),
                subtitle: Text(
                  '${card.brand.toUpperCase()} · ${card.expMonth}/${card.expYear}',
                ),
                trailing: _selectedCardId == card.id
                    ? Icon(Icons.check_circle, color: palette.brand)
                    : null,
                onTap: () => setState(() => _selectedCardId = card.id),
              ),
            TextButton.icon(
              onPressed: () => setState(() => _showAddCard = true),
              icon: const Icon(Icons.add_rounded),
              label: Text(l10n.walletAddCard),
            ),
          ] else ...[
            TextField(
              controller: _cardController,
              keyboardType: TextInputType.number,
              inputFormatters: [
                FilteringTextInputFormatter.digitsOnly,
                LengthLimitingTextInputFormatter(16),
                _CardNumberFormatter(),
              ],
              decoration: InputDecoration(
                labelText: l10n.walletCardNumberLabel,
                hintText: '8600 0000 0000 0000',
                prefixIcon: const Icon(Icons.credit_card_rounded),
              ),
            ),
            const SizedBox(height: AppSpacing.md),
            TextField(
              controller: _holderController,
              textCapitalization: TextCapitalization.characters,
              decoration: InputDecoration(
                labelText: l10n.walletCardHolderLabel,
                prefixIcon: const Icon(Icons.person_outline_rounded),
              ),
            ),
            const SizedBox(height: AppSpacing.md),
            Row(
              children: [
                Expanded(
                  child: TextField(
                    controller: _expMonthController,
                    keyboardType: TextInputType.number,
                    inputFormatters: [
                      FilteringTextInputFormatter.digitsOnly,
                      LengthLimitingTextInputFormatter(2),
                    ],
                    decoration: InputDecoration(
                      labelText: l10n.walletCardExpMonth,
                    ),
                  ),
                ),
                const SizedBox(width: AppSpacing.md),
                Expanded(
                  child: TextField(
                    controller: _expYearController,
                    keyboardType: TextInputType.number,
                    inputFormatters: [
                      FilteringTextInputFormatter.digitsOnly,
                      LengthLimitingTextInputFormatter(4),
                    ],
                    decoration: InputDecoration(
                      labelText: l10n.walletCardExpYear,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: AppSpacing.md),
            Row(
              children: [
                TextButton(
                  onPressed: () => setState(() => _showAddCard = false),
                  child: Text(l10n.walletCancelAddCard),
                ),
                const Spacer(),
                FilledButton(
                  onPressed: state.isSubmitting ? null : _saveCard,
                  child: Text(l10n.walletSaveCard),
                ),
              ],
            ),
          ],
        ],
        const SizedBox(height: AppSpacing.xl),
        GradientButton(
          label: l10n.walletTopUp,
          icon: Icons.add_rounded,
          busy: state.isSubmitting,
          onPressed: _canSubmit ? _submit : null,
        ),
      ],
    );
  }
}

class _BitcoinPaymentDialog extends ConsumerStatefulWidget {
  const _BitcoinPaymentDialog({required this.start});

  final TopUpStart start;

  @override
  ConsumerState<_BitcoinPaymentDialog> createState() =>
      _BitcoinPaymentDialogState();
}

class _BitcoinPaymentDialogState extends ConsumerState<_BitcoinPaymentDialog> {
  TopUpOutcome? _pollOutcome;
  bool _confirming = false;

  @override
  void initState() {
    super.initState();
    final id = widget.start.transactionId;
    if (id != null) {
      ref.read(walletNotifierProvider.notifier).awaitTopUp(id).then((outcome) {
        if (!mounted) return;
        if (outcome == TopUpOutcome.credited ||
            outcome == TopUpOutcome.failed) {
          Navigator.of(context).pop(outcome);
        } else {
          setState(() => _pollOutcome = outcome);
        }
      });
    }
  }

  Future<void> _copyAddress() async {
    final address = widget.start.cryptoAddress;
    if (address == null) return;
    await Clipboard.setData(ClipboardData(text: address));
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(AppLocalizations.of(context)!.walletAddressCopied),
      ),
    );
  }

  Future<void> _confirmSandbox() async {
    final id = widget.start.transactionId;
    if (id == null) return;
    setState(() => _confirming = true);
    final outcome =
        await ref.read(walletNotifierProvider.notifier).confirmSandboxTopUp(id);
    if (mounted) {
      Navigator.of(context).pop(outcome);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final palette = AppPalette.of(context);
    final qr = widget.start.qrPayload ??
        'bitcoin:${widget.start.cryptoAddress}?amount=${widget.start.cryptoAmount}';

    return AlertDialog(
      title: Text(l10n.walletBitcoinPayTitle),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            QrImageView(
              data: qr,
              size: 200,
              backgroundColor: Colors.white,
            ),
            const SizedBox(height: AppSpacing.md),
            Text(
              '${widget.start.cryptoAmount ?? ''} ${widget.start.cryptoCurrency ?? 'BTC'}',
              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
            ),
            const SizedBox(height: AppSpacing.sm),
            SelectableText(
              widget.start.cryptoAddress ?? '',
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: palette.textSecondary,
                  ),
              textAlign: TextAlign.center,
            ),
            if (widget.start.expiresAt != null) ...[
              const SizedBox(height: AppSpacing.sm),
              Text(
                l10n.walletBitcoinExpires(widget.start.expiresAt!),
                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: palette.textTertiary,
                    ),
              ),
            ],
            const SizedBox(height: AppSpacing.md),
            Text(
              l10n.walletTopUpWaiting,
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: palette.textSecondary,
                  ),
              textAlign: TextAlign.center,
            ),
            if (_pollOutcome == TopUpOutcome.awaitingProvider)
              Padding(
                padding: const EdgeInsets.only(top: AppSpacing.sm),
                child: Text(
                  l10n.walletTopUpPending,
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: palette.danger,
                      ),
                  textAlign: TextAlign.center,
                ),
              ),
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: _copyAddress,
          child: Text(l10n.walletCopyAddress),
        ),
        if (widget.start.sandboxConfirm)
          FilledButton(
            onPressed: _confirming ? null : _confirmSandbox,
            child: Text(l10n.walletBitcoinConfirmPaid),
          )
        else
          TextButton(
            onPressed: () => Navigator.of(context).pop(TopUpOutcome.awaitingProvider),
            child: Text(l10n.walletBitcoinClose),
          ),
      ],
    );
  }
}

class _WithdrawSheet extends ConsumerStatefulWidget {
  const _WithdrawSheet({required this.wallet});

  final Wallet wallet;

  @override
  ConsumerState<_WithdrawSheet> createState() => _WithdrawSheetState();
}

class _WithdrawSheetState extends ConsumerState<_WithdrawSheet> {
  final _amountController = TextEditingController();
  final _cardController = TextEditingController();
  final _holderController = TextEditingController();

  @override
  void initState() {
    super.initState();
    _amountController.addListener(_refresh);
    _cardController.addListener(_refresh);
    _holderController.addListener(_refresh);
  }

  @override
  void dispose() {
    _amountController.dispose();
    _cardController.dispose();
    _holderController.dispose();
    super.dispose();
  }

  void _refresh() => setState(() {});

  int get _amount => int.tryParse(_amountController.text.trim()) ?? 0;

  int get _fee => widget.wallet.limits.feeFor(_amount);

  int get _total => _amount + _fee;

  String get _digits => _cardController.text.replaceAll(RegExp(r'\D'), '');

  bool get _isValid {
    final limits = widget.wallet.limits;
    return _amount >= limits.withdrawalMin &&
        _amount <= limits.withdrawalMax &&
        _total <= widget.wallet.available.amount &&
        _digits.length >= 12 &&
        _holderController.text.trim().length >= 3;
  }

  Future<void> _submit() async {
    final ok = await ref.read(walletNotifierProvider.notifier).withdraw(
          amount: _amount,
          method: 'card',
          cardNumber: _digits,
          cardHolder: _holderController.text.trim(),
        );

    if (ok && mounted) {
      Navigator.of(context).pop(true);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final state = ref.watch(walletNotifierProvider);
    final limits = widget.wallet.limits;
    final currency = widget.wallet.currency;

    return _SheetShell(
      title: l10n.walletWithdrawTitle,
      children: [
        _AmountField(
          controller: _amountController,
          currency: currency,
          autofocus: true,
        ),
        const SizedBox(height: AppSpacing.sm),
        _LimitsHint(
          min: limits.withdrawalMin,
          max: limits.withdrawalMax,
          currency: currency,
        ),
        const SizedBox(height: AppSpacing.lg),
        TextField(
          controller: _cardController,
          keyboardType: TextInputType.number,
          inputFormatters: [
            FilteringTextInputFormatter.digitsOnly,
            LengthLimitingTextInputFormatter(16),
            _CardNumberFormatter(),
          ],
          decoration: InputDecoration(
            labelText: l10n.walletCardNumberLabel,
            hintText: '8600 0000 0000 0000',
            prefixIcon: const Icon(Icons.credit_card_rounded),
          ),
        ),
        const SizedBox(height: AppSpacing.md),
        TextField(
          controller: _holderController,
          textCapitalization: TextCapitalization.characters,
          decoration: InputDecoration(
            labelText: l10n.walletCardHolderLabel,
            prefixIcon: const Icon(Icons.person_outline_rounded),
          ),
        ),
        if (_amount > 0) ...[
          const SizedBox(height: AppSpacing.lg),
          _WithdrawSummary(
            fee: _fee,
            total: _total,
            currency: currency,
            exceedsBalance: _total > widget.wallet.available.amount,
          ),
        ],
        const SizedBox(height: AppSpacing.xl),
        GradientButton(
          label: l10n.walletWithdraw,
          icon: Icons.arrow_outward_rounded,
          busy: state.isSubmitting,
          onPressed: _isValid ? _submit : null,
        ),
      ],
    );
  }
}

class _WithdrawSummary extends StatelessWidget {
  const _WithdrawSummary({
    required this.fee,
    required this.total,
    required this.currency,
    required this.exceedsBalance,
  });

  final int fee;
  final int total;
  final String currency;
  final bool exceedsBalance;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final palette = AppPalette.of(context);
    final theme = Theme.of(context);

    return Container(
      padding: const EdgeInsets.all(AppSpacing.md),
      decoration: BoxDecoration(
        color: palette.surfaceHigh,
        borderRadius: AppRadius.smAll,
      ),
      child: Column(
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                l10n.walletFeeLabel,
                style: theme.textTheme.bodyMedium
                    ?.copyWith(color: palette.textSecondary),
              ),
              Text(formatPrice(fee, currency), style: theme.textTheme.bodyMedium),
            ],
          ),
          const SizedBox(height: AppSpacing.xs),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                l10n.walletTotalDebit,
                style: theme.textTheme.bodyMedium
                    ?.copyWith(color: palette.textSecondary),
              ),
              Text(
                formatPrice(total, currency),
                style: theme.textTheme.titleSmall?.copyWith(
                  fontWeight: FontWeight.w700,
                  color: exceedsBalance ? palette.danger : palette.textPrimary,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _PresetChip extends StatelessWidget {
  const _PresetChip({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final palette = AppPalette.of(context);

    return Material(
      color: selected
          ? palette.brand.withValues(alpha: 0.12)
          : palette.surfaceHigh,
      borderRadius: AppRadius.pillAll,
      child: InkWell(
        onTap: onTap,
        borderRadius: AppRadius.pillAll,
        child: Container(
          padding: const EdgeInsets.symmetric(
            horizontal: AppSpacing.md,
            vertical: AppSpacing.sm,
          ),
          decoration: BoxDecoration(
            borderRadius: AppRadius.pillAll,
            border: Border.all(
              color: selected ? palette.brand : Colors.transparent,
            ),
          ),
          child: Text(
            label,
            style: Theme.of(context).textTheme.labelLarge?.copyWith(
                  color: selected ? palette.brand : palette.textSecondary,
                  fontWeight: FontWeight.w600,
                ),
          ),
        ),
      ),
    );
  }
}

class _MethodTile extends StatelessWidget {
  const _MethodTile({
    required this.icon,
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final palette = AppPalette.of(context);

    return Material(
      color: selected
          ? palette.brand.withValues(alpha: 0.1)
          : palette.surfaceHigh,
      borderRadius: AppRadius.smAll,
      child: InkWell(
        onTap: onTap,
        borderRadius: AppRadius.smAll,
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: AppSpacing.md),
          decoration: BoxDecoration(
            borderRadius: AppRadius.smAll,
            border: Border.all(
              color: selected ? palette.brand : Colors.transparent,
            ),
          ),
          child: Column(
            children: [
              Icon(
                icon,
                size: 20,
                color: selected ? palette.brand : palette.textSecondary,
              ),
              const SizedBox(height: AppSpacing.xs),
              Text(
                label,
                style: Theme.of(context).textTheme.labelSmall?.copyWith(
                      color: selected ? palette.brand : palette.textSecondary,
                    ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Groups the PAN into 4-digit blocks while typing.
class _CardNumberFormatter extends TextInputFormatter {
  @override
  TextEditingValue formatEditUpdate(
    TextEditingValue oldValue,
    TextEditingValue newValue,
  ) {
    final digits = newValue.text.replaceAll(RegExp(r'\D'), '');
    final buffer = StringBuffer();

    for (var i = 0; i < digits.length; i++) {
      if (i > 0 && i % 4 == 0) {
        buffer.write(' ');
      }
      buffer.write(digits[i]);
    }

    final text = buffer.toString();

    return TextEditingValue(
      text: text,
      selection: TextSelection.collapsed(offset: text.length),
    );
  }
}
