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

Future<bool?> showWalletTopUpSheet(BuildContext context, Wallet wallet) {
  return showModalBottomSheet<bool>(
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

/// Presets keep the common amounts one tap away instead of typing six zeros.
const _topUpPresets = <int>[50000, 100000, 250000, 500000, 1000000];

const _topUpMethods = <({String id, String label, IconData icon})>[
  (id: 'click', label: 'Click', icon: Icons.bolt_rounded),
  (id: 'payme', label: 'Payme', icon: Icons.qr_code_rounded),
  (id: 'uzum', label: 'Uzum', icon: Icons.account_balance_wallet_outlined),
  (id: 'card', label: 'Card', icon: Icons.credit_card_rounded),
];

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
  String _method = _topUpMethods.first.id;

  @override
  void dispose() {
    _amountController.dispose();
    super.dispose();
  }

  int get _amount => int.tryParse(_amountController.text.trim()) ?? 0;

  bool get _isValid =>
      _amount >= widget.wallet.limits.topUpMin &&
      _amount <= widget.wallet.limits.topUpMax;

  Future<void> _submit() async {
    final ok = await ref
        .read(walletNotifierProvider.notifier)
        .topUp(amount: _amount, method: _method);

    if (ok && mounted) {
      Navigator.of(context).pop(true);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final state = ref.watch(walletNotifierProvider);
    final limits = widget.wallet.limits;

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
                onTap: () => setState(() {
                  _amountController.text = '$preset';
                }),
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
            for (final method in _topUpMethods)
              Expanded(
                child: Padding(
                  padding: const EdgeInsets.only(right: AppSpacing.sm),
                  child: _MethodTile(
                    icon: method.icon,
                    label: method.label,
                    selected: _method == method.id,
                    onTap: () => setState(() => _method = method.id),
                  ),
                ),
              ),
          ],
        ),
        const SizedBox(height: AppSpacing.xl),
        GradientButton(
          label: l10n.walletTopUp,
          icon: Icons.add_rounded,
          busy: state.isSubmitting,
          onPressed: _isValid ? _submit : null,
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
