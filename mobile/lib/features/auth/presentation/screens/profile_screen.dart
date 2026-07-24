import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:livecommerce_mobile/core/l10n/app_localizations.dart';
import 'package:livecommerce_mobile/core/theme/app_colors.dart';
import 'package:livecommerce_mobile/core/theme/app_dimens.dart';
import 'package:livecommerce_mobile/features/auth/presentation/providers/auth_providers.dart';
import 'package:livecommerce_mobile/features/messaging/presentation/providers/messaging_providers.dart';
import 'package:livecommerce_mobile/features/wallet/presentation/providers/wallet_providers.dart';
import 'package:livecommerce_mobile/shared/widgets/gradient_button.dart';
import 'package:livecommerce_mobile/shared/widgets/user_avatar.dart';
import 'package:sentry_flutter/sentry_flutter.dart';

class ProfileScreen extends ConsumerStatefulWidget {
  const ProfileScreen({super.key});

  @override
  ConsumerState<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends ConsumerState<ProfileScreen> {
  final _displayNameController = TextEditingController();
  final _bioController = TextEditingController();
  bool _initialized = false;

  @override
  void initState() {
    super.initState();
    Future.microtask(() => ref.read(walletNotifierProvider.notifier).load());
  }

  @override
  void dispose() {
    _displayNameController.dispose();
    _bioController.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    final success = await ref.read(authNotifierProvider.notifier).updateProfile(
          displayName: _displayNameController.text.trim(),
          bio: _bioController.text.trim(),
        );

    if (!mounted) {
      return;
    }

    if (success) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(AppLocalizations.of(context)!.saveButton)),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final auth = ref.watch(authNotifierProvider);
    final user = auth.user;

    if (user != null && !_initialized) {
      _displayNameController.text = user.displayName ?? user.username;
      _bioController.text = user.bio ?? '';
      _initialized = true;
    }

    if (user == null) {
      return Scaffold(body: Center(child: Text(l10n.authRequired)));
    }

    final palette = AppPalette.of(context);
    final theme = Theme.of(context);
    final unread = ref.watch(unreadCountProvider).valueOrNull ?? 0;
    final wallet = ref.watch(walletNotifierProvider).wallet;

    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.profileTitle),
        actions: [
          IconButton(
            onPressed: () => context.push('/settings'),
            icon: const Icon(Icons.settings_outlined),
            tooltip: l10n.settingsTitle,
          ),
        ],
      ),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.fromLTRB(
            AppSpacing.lg,
            AppSpacing.lg,
            AppSpacing.lg,
            AppSpacing.xxxl,
          ),
          children: [
            Column(
              children: [
                UserAvatar(
                  username: user.username,
                  avatarUrl: user.avatarUrl,
                  radius: 44,
                  ring: true,
                ),
                const SizedBox(height: AppSpacing.md),
                Text(
                  user.displayName ?? user.username,
                  style: theme.textTheme.titleLarge
                      ?.copyWith(fontWeight: FontWeight.w700),
                ),
                Text(
                  '@${user.username}',
                  style: theme.textTheme.bodyMedium
                      ?.copyWith(color: palette.textTertiary),
                ),
              ],
            ),
            const SizedBox(height: AppSpacing.xl),
            _WalletPreviewCard(
              balance: wallet?.available.format(),
              onTap: () => context.push('/wallet'),
            ),
            const SizedBox(height: AppSpacing.xl),
            _ProfileCard(
              children: [
                _NavRow(
                  icon: Icons.receipt_long_outlined,
                  label: l10n.myOrders,
                  onTap: () => context.push('/orders'),
                ),
                _NavRow(
                  icon: Icons.chat_bubble_outline_rounded,
                  label: l10n.messagesTitle,
                  badge: unread > 0 ? '$unread' : null,
                  onTap: () => context.push('/conversations'),
                ),
                _NavRow(
                  icon: Icons.storefront_outlined,
                  label: user.isSeller ? l10n.sellerCenter : l10n.becomeSeller,
                  onTap: () => context.push(
                    user.isSeller ? '/seller' : '/seller/apply',
                  ),
                  isLast: true,
                ),
              ],
            ),
            const SizedBox(height: AppSpacing.xl),
            Text(
              l10n.profileTitle,
              style: theme.textTheme.titleMedium
                  ?.copyWith(fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: AppSpacing.md),
            TextFormField(
              key: const Key('display_name_field'),
              controller: _displayNameController,
              decoration: InputDecoration(labelText: l10n.displayNameLabel),
            ),
            const SizedBox(height: AppSpacing.md),
            TextFormField(
              key: const Key('bio_field'),
              controller: _bioController,
              maxLines: 3,
              decoration: InputDecoration(labelText: l10n.bioLabel),
            ),
            const SizedBox(height: AppSpacing.lg),
            GradientButton(
              label: l10n.saveButton,
              busy: auth.isLoading,
              onPressed: auth.isLoading ? null : _save,
            ),
            if (kDebugMode) ...[
              const SizedBox(height: AppSpacing.xxl),
              OutlinedButton(
                onPressed: () {
                  Sentry.captureException(StateError('This is test exception'));
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(content: Text('Sentry test event sent')),
                  );
                },
                child: const Text('Verify Sentry Setup'),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _WalletPreviewCard extends StatelessWidget {
  const _WalletPreviewCard({required this.onTap, this.balance});

  final VoidCallback onTap;
  final String? balance;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final theme = Theme.of(context);

    return Material(
      color: Colors.transparent,
      borderRadius: AppRadius.lgAll,
      child: InkWell(
        onTap: onTap,
        borderRadius: AppRadius.lgAll,
        child: Ink(
          decoration: BoxDecoration(
            gradient: AppGradients.brandDiagonal,
            borderRadius: AppRadius.lgAll,
            boxShadow: AppShadows.brandGlow(opacity: 0.22),
          ),
          child: Padding(
            padding: const EdgeInsets.all(AppSpacing.lg),
            child: Row(
              children: [
                const Icon(
                  Icons.account_balance_wallet_outlined,
                  color: AppColors.onMedia,
                ),
                const SizedBox(width: AppSpacing.md),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        l10n.walletTitle,
                        style: theme.textTheme.labelLarge?.copyWith(
                          color: AppColors.onMediaMuted,
                        ),
                      ),
                      Text(
                        balance ?? '—',
                        style: theme.textTheme.titleLarge?.copyWith(
                          color: AppColors.onMedia,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ],
                  ),
                ),
                const Icon(
                  Icons.chevron_right_rounded,
                  color: AppColors.onMediaMuted,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _ProfileCard extends StatelessWidget {
  const _ProfileCard({required this.children});

  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    final palette = AppPalette.of(context);

    return Container(
      decoration: BoxDecoration(
        color: palette.surfaceElevated,
        borderRadius: AppRadius.lgAll,
        border: Border.all(color: palette.outline),
      ),
      child: Column(children: children),
    );
  }
}

class _NavRow extends StatelessWidget {
  const _NavRow({
    required this.icon,
    required this.label,
    required this.onTap,
    this.badge,
    this.isLast = false,
  });

  final IconData icon;
  final String label;
  final VoidCallback onTap;
  final String? badge;
  final bool isLast;

  @override
  Widget build(BuildContext context) {
    final palette = AppPalette.of(context);
    final theme = Theme.of(context);

    return InkWell(
      onTap: onTap,
      borderRadius: isLast
          ? const BorderRadius.vertical(bottom: Radius.circular(AppRadius.lg))
          : null,
      child: Container(
        padding: const EdgeInsets.symmetric(
          horizontal: AppSpacing.lg,
          vertical: AppSpacing.md + 2,
        ),
        decoration: BoxDecoration(
          border: isLast
              ? null
              : Border(bottom: BorderSide(color: palette.outline)),
        ),
        child: Row(
          children: [
            Icon(icon, size: 20, color: palette.textSecondary),
            const SizedBox(width: AppSpacing.md),
            Expanded(child: Text(label, style: theme.textTheme.bodyLarge)),
            if (badge != null) ...[
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: AppSpacing.sm,
                  vertical: 1,
                ),
                decoration: const BoxDecoration(
                  gradient: AppGradients.brand,
                  borderRadius: AppRadius.pillAll,
                ),
                child: Text(
                  badge!,
                  style: theme.textTheme.labelSmall?.copyWith(
                    color: AppColors.onMedia,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
              const SizedBox(width: AppSpacing.sm),
            ],
            Icon(
              Icons.chevron_right_rounded,
              size: 20,
              color: palette.textTertiary,
            ),
          ],
        ),
      ),
    );
  }
}
