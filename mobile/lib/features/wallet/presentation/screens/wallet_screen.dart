import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:livecommerce_mobile/core/l10n/app_localizations.dart';
import 'package:livecommerce_mobile/core/theme/app_colors.dart';
import 'package:livecommerce_mobile/core/theme/app_dimens.dart';
import 'package:livecommerce_mobile/core/utils/time_format.dart';
import 'package:livecommerce_mobile/features/wallet/domain/entities/wallet.dart';
import 'package:livecommerce_mobile/features/wallet/presentation/providers/wallet_providers.dart';
import 'package:livecommerce_mobile/features/wallet/presentation/widgets/wallet_sheets.dart';
import 'package:livecommerce_mobile/shared/widgets/empty_state.dart';
import 'package:livecommerce_mobile/shared/widgets/error_widget.dart';

class WalletScreen extends ConsumerStatefulWidget {
  const WalletScreen({super.key});

  @override
  ConsumerState<WalletScreen> createState() => _WalletScreenState();
}

class _WalletScreenState extends ConsumerState<WalletScreen> {
  final _scrollController = ScrollController();

  @override
  void initState() {
    super.initState();
    _scrollController.addListener(_onScroll);
    Future.microtask(() => ref.read(walletNotifierProvider.notifier).load());
  }

  @override
  void dispose() {
    _scrollController.dispose();
    super.dispose();
  }

  void _onScroll() {
    if (_scrollController.position.pixels >=
        _scrollController.position.maxScrollExtent - 240) {
      ref.read(walletNotifierProvider.notifier).loadMore();
    }
  }

  Future<void> _openTopUp(Wallet wallet) async {
    final l10n = AppLocalizations.of(context)!;
    final result = await showWalletTopUpSheet(context, wallet);
    if (result == true && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(l10n.walletTopUpSuccess)),
      );
    }
  }

  Future<void> _openWithdraw(Wallet wallet) async {
    final l10n = AppLocalizations.of(context)!;
    final result = await showWalletWithdrawSheet(context, wallet);
    if (result == true && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(l10n.walletWithdrawSuccess)),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final state = ref.watch(walletNotifierProvider);
    final wallet = state.wallet;

    return Scaffold(
      appBar: AppBar(title: Text(l10n.walletTitle)),
      body: _buildBody(context, state, wallet, l10n),
    );
  }

  Widget _buildBody(
    BuildContext context,
    WalletState state,
    Wallet? wallet,
    AppLocalizations l10n,
  ) {
    if (state.isLoading && wallet == null) {
      return const Center(child: CircularProgressIndicator());
    }

    if (wallet == null) {
      return ErrorDisplay(
        message: state.error ?? '—',
        onRetry: () => ref.read(walletNotifierProvider.notifier).load(),
      );
    }

    final pending = state.pendingWithdrawals;

    return RefreshIndicator(
      onRefresh: () => ref.read(walletNotifierProvider.notifier).load(),
      child: ListView(
        controller: _scrollController,
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(
          AppSpacing.lg,
          AppSpacing.lg,
          AppSpacing.lg,
          AppSpacing.xxxl,
        ),
        children: [
          _BalanceCard(
            wallet: wallet,
            busy: state.isSubmitting,
            onTopUp: () => _openTopUp(wallet),
            onWithdraw: () => _openWithdraw(wallet),
          ),
          if (state.error != null) ...[
            const SizedBox(height: AppSpacing.md),
            _InlineError(
              message: state.error!,
              onDismiss: () =>
                  ref.read(walletNotifierProvider.notifier).clearError(),
            ),
          ],
          if (pending.isNotEmpty) ...[
            const SizedBox(height: AppSpacing.xxl),
            _SectionTitle(l10n.walletPendingWithdrawals),
            const SizedBox(height: AppSpacing.md),
            for (final withdrawal in pending)
              Padding(
                padding: const EdgeInsets.only(bottom: AppSpacing.sm),
                child: _PendingWithdrawalCard(
                  withdrawal: withdrawal,
                  busy: state.isSubmitting,
                  onCancel: () => ref
                      .read(walletNotifierProvider.notifier)
                      .cancelWithdrawal(withdrawal.id),
                ),
              ),
          ],
          const SizedBox(height: AppSpacing.xxl),
          _SectionTitle(l10n.walletHistory),
          const SizedBox(height: AppSpacing.md),
          if (state.transactions.isEmpty)
            Padding(
              padding: const EdgeInsets.only(top: AppSpacing.xxl),
              child: EmptyState(
                title: l10n.walletNoTransactions,
                subtitle: l10n.walletNoTransactionsSubtitle,
                icon: Icons.receipt_long_outlined,
              ),
            )
          else
            for (final transaction in state.transactions)
              _TransactionTile(transaction: transaction),
          if (state.isLoadingMore)
            const Padding(
              padding: EdgeInsets.all(AppSpacing.lg),
              child: Center(child: CircularProgressIndicator()),
            ),
        ],
      ),
    );
  }
}

class _BalanceCard extends StatelessWidget {
  const _BalanceCard({
    required this.wallet,
    required this.busy,
    required this.onTopUp,
    required this.onWithdraw,
  });

  final Wallet wallet;
  final bool busy;
  final VoidCallback onTopUp;
  final VoidCallback onWithdraw;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final theme = Theme.of(context);
    final hasHeld = wallet.held.amount > 0;

    return Container(
      padding: const EdgeInsets.all(AppSpacing.xl),
      decoration: BoxDecoration(
        gradient: AppGradients.brandDiagonal,
        borderRadius: AppRadius.xlAll,
        boxShadow: AppShadows.brandGlow(opacity: 0.28),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(
                Icons.account_balance_wallet_outlined,
                color: AppColors.onMedia,
                size: 20,
              ),
              const SizedBox(width: AppSpacing.sm),
              Text(
                l10n.walletBalance,
                style: theme.textTheme.labelLarge?.copyWith(
                  color: AppColors.onMediaMuted,
                ),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.sm),
          FittedBox(
            fit: BoxFit.scaleDown,
            alignment: Alignment.centerLeft,
            child: Text(
              wallet.available.format(),
              style: theme.textTheme.displaySmall?.copyWith(
                color: AppColors.onMedia,
                fontWeight: FontWeight.w800,
                letterSpacing: -0.5,
              ),
            ),
          ),
          if (hasHeld) ...[
            const SizedBox(height: AppSpacing.xs),
            Text(
              '${l10n.walletHeld}: ${wallet.held.format()}',
              style: theme.textTheme.bodySmall?.copyWith(
                color: AppColors.onMediaMuted,
              ),
            ),
          ],
          const SizedBox(height: AppSpacing.xl),
          Row(
            children: [
              Expanded(
                child: _GlassAction(
                  icon: Icons.add_rounded,
                  label: l10n.walletTopUp,
                  onTap: busy ? null : onTopUp,
                ),
              ),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                child: _GlassAction(
                  icon: Icons.arrow_outward_rounded,
                  label: l10n.walletWithdraw,
                  onTap: busy || wallet.available.amount <= 0
                      ? null
                      : onWithdraw,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _GlassAction extends StatelessWidget {
  const _GlassAction({required this.icon, required this.label, this.onTap});

  final IconData icon;
  final String label;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return Opacity(
      opacity: onTap == null ? 0.5 : 1,
      child: Material(
        color: Colors.white.withValues(alpha: 0.18),
        borderRadius: AppRadius.mdAll,
        child: InkWell(
          onTap: onTap,
          borderRadius: AppRadius.mdAll,
          child: Container(
            height: 46,
            alignment: Alignment.center,
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(icon, size: 18, color: AppColors.onMedia),
                const SizedBox(width: AppSpacing.sm),
                Text(
                  label,
                  style: const TextStyle(
                    color: AppColors.onMedia,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _SectionTitle extends StatelessWidget {
  const _SectionTitle(this.title);

  final String title;

  @override
  Widget build(BuildContext context) {
    return Text(
      title,
      style: Theme.of(context).textTheme.titleMedium?.copyWith(
            fontWeight: FontWeight.w700,
          ),
    );
  }
}

class _InlineError extends StatelessWidget {
  const _InlineError({required this.message, required this.onDismiss});

  final String message;
  final VoidCallback onDismiss;

  @override
  Widget build(BuildContext context) {
    final palette = AppPalette.of(context);

    return Container(
      padding: const EdgeInsets.fromLTRB(
        AppSpacing.md,
        AppSpacing.sm,
        AppSpacing.sm,
        AppSpacing.sm,
      ),
      decoration: BoxDecoration(
        color: palette.danger.withValues(alpha: 0.1),
        borderRadius: AppRadius.smAll,
        border: Border.all(color: palette.danger.withValues(alpha: 0.35)),
      ),
      child: Row(
        children: [
          Icon(Icons.error_outline_rounded, size: 18, color: palette.danger),
          const SizedBox(width: AppSpacing.sm),
          Expanded(
            child: Text(
              message,
              style: Theme.of(context)
                  .textTheme
                  .bodySmall
                  ?.copyWith(color: palette.danger),
            ),
          ),
          IconButton(
            onPressed: onDismiss,
            visualDensity: VisualDensity.compact,
            icon: Icon(Icons.close_rounded, size: 16, color: palette.danger),
          ),
        ],
      ),
    );
  }
}

class _PendingWithdrawalCard extends StatelessWidget {
  const _PendingWithdrawalCard({
    required this.withdrawal,
    required this.busy,
    required this.onCancel,
  });

  final WalletWithdrawal withdrawal;
  final bool busy;
  final VoidCallback onCancel;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final palette = AppPalette.of(context);
    final theme = Theme.of(context);

    return Container(
      padding: const EdgeInsets.all(AppSpacing.md),
      decoration: BoxDecoration(
        color: palette.surfaceElevated,
        borderRadius: AppRadius.lgAll,
        border: Border.all(color: palette.outline),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.all(AppSpacing.sm),
                decoration: BoxDecoration(
                  color: palette.warning.withValues(alpha: 0.14),
                  borderRadius: AppRadius.smAll,
                ),
                child: Icon(
                  Icons.schedule_rounded,
                  size: 18,
                  color: palette.warning,
                ),
              ),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      withdrawal.amount.format(),
                      style: theme.textTheme.titleSmall
                          ?.copyWith(fontWeight: FontWeight.w700),
                    ),
                    Text(
                      l10n.walletCardMask(withdrawal.cardLast4),
                      style: theme.textTheme.bodySmall
                          ?.copyWith(color: palette.textTertiary),
                    ),
                  ],
                ),
              ),
              Text(
                withdrawal.status == WithdrawalStatus.processing
                    ? l10n.walletStatusProcessing
                    : l10n.walletStatusRequested,
                style: theme.textTheme.labelMedium
                    ?.copyWith(color: palette.warning),
              ),
            ],
          ),
          if (withdrawal.status == WithdrawalStatus.requested) ...[
            const SizedBox(height: AppSpacing.sm),
            Align(
              alignment: Alignment.centerRight,
              child: TextButton(
                onPressed: busy ? null : onCancel,
                child: Text(l10n.walletCancelWithdrawal),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _TransactionTile extends StatelessWidget {
  const _TransactionTile({required this.transaction});

  final WalletTransaction transaction;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final palette = AppPalette.of(context);
    final theme = Theme.of(context);
    final failed = transaction.status == WalletTransactionStatus.failed ||
        transaction.status == WalletTransactionStatus.cancelled;
    final pending = transaction.status == WalletTransactionStatus.pending;

    final amountColor = failed
        ? palette.textTertiary
        : transaction.isCredit
            ? palette.success
            : palette.textPrimary;

    return Padding(
      padding: const EdgeInsets.only(bottom: AppSpacing.md),
      child: Row(
        children: [
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: _iconColor(palette).withValues(alpha: 0.12),
              borderRadius: AppRadius.smAll,
            ),
            child: Icon(_icon, size: 20, color: _iconColor(palette)),
          ),
          const SizedBox(width: AppSpacing.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _title(l10n),
                  style: theme.textTheme.titleSmall,
                ),
                Text(
                  [
                    formatTimeAgo(l10n, transaction.createdAt),
                    if (pending) l10n.walletStatusPending,
                    if (failed) _statusLabel(l10n),
                  ].where((part) => part.isNotEmpty).join(' · '),
                  style: theme.textTheme.bodySmall
                      ?.copyWith(color: palette.textTertiary),
                ),
              ],
            ),
          ),
          Text(
            '${transaction.isCredit ? '+' : '−'}${transaction.amount.format()}',
            style: theme.textTheme.titleSmall?.copyWith(
              color: amountColor,
              fontWeight: FontWeight.w700,
              decoration: failed ? TextDecoration.lineThrough : null,
            ),
          ),
        ],
      ),
    );
  }

  IconData get _icon => switch (transaction.type) {
        WalletTransactionType.topUp => Icons.add_rounded,
        WalletTransactionType.withdrawal => Icons.arrow_outward_rounded,
        WalletTransactionType.orderPayment => Icons.shopping_bag_outlined,
        WalletTransactionType.refund => Icons.undo_rounded,
        WalletTransactionType.payout => Icons.payments_outlined,
        WalletTransactionType.adjustment => Icons.tune_rounded,
      };

  Color _iconColor(AppPalette palette) {
    if (transaction.status == WalletTransactionStatus.failed ||
        transaction.status == WalletTransactionStatus.cancelled) {
      return palette.textTertiary;
    }
    return transaction.isCredit ? palette.success : palette.brand;
  }

  String _title(AppLocalizations l10n) => switch (transaction.type) {
        WalletTransactionType.topUp => l10n.walletTypeTopUp,
        WalletTransactionType.withdrawal => l10n.walletTypeWithdrawal,
        WalletTransactionType.orderPayment => l10n.walletTypeOrderPayment,
        WalletTransactionType.refund => l10n.walletTypeRefund,
        WalletTransactionType.payout => l10n.walletTypePayout,
        WalletTransactionType.adjustment => l10n.walletTypeAdjustment,
      };

  String _statusLabel(AppLocalizations l10n) =>
      switch (transaction.status) {
        WalletTransactionStatus.failed => l10n.walletStatusFailed,
        WalletTransactionStatus.cancelled => l10n.walletStatusCancelled,
        WalletTransactionStatus.completed => l10n.walletStatusCompleted,
        WalletTransactionStatus.pending => l10n.walletStatusPending,
      };
}
