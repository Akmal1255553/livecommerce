import 'package:flutter/material.dart';
import 'package:flutter_gen/gen_l10n/app_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:livecommerce_mobile/features/auth/presentation/providers/auth_providers.dart';

class OtpScreen extends ConsumerStatefulWidget {
  const OtpScreen({super.key, required this.phone});

  final String phone;

  @override
  ConsumerState<OtpScreen> createState() => _OtpScreenState();
}

class _OtpScreenState extends ConsumerState<OtpScreen> {
  final _otpController = TextEditingController();

  @override
  void dispose() {
    _otpController.dispose();
    super.dispose();
  }

  Future<void> _verify() async {
    final success = await ref.read(authNotifierProvider.notifier).verifyOtp(
          widget.phone,
          _otpController.text.trim(),
        );

    if (!mounted) {
      return;
    }

    if (success) {
      context.go('/home');
    }
  }

  Future<void> _resend() async {
    await ref.read(authNotifierProvider.notifier).resendOtp(widget.phone);
    if (!mounted) {
      return;
    }
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(AppLocalizations.of(context)!.resendOtpButton)),
    );
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final auth = ref.watch(authNotifierProvider);

    return Scaffold(
      appBar: AppBar(title: Text(l10n.otpTitle)),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(widget.phone, style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 16),
              TextField(
                key: const Key('otp_field'),
                controller: _otpController,
                keyboardType: TextInputType.number,
                maxLength: 6,
                decoration: InputDecoration(labelText: l10n.otpLabel),
              ),
              if (auth.error != null) ...[
                Text(auth.error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
                const SizedBox(height: 8),
              ],
              FilledButton(
                onPressed: auth.isLoading ? null : _verify,
                child: Text(l10n.verifyOtpButton),
              ),
              TextButton(
                onPressed: auth.isLoading ? null : _resend,
                child: Text(l10n.resendOtpButton),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
