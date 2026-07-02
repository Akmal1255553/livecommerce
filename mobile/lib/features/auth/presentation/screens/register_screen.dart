import 'package:flutter/material.dart';
import 'package:livecommerce_mobile/core/l10n/app_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:livecommerce_mobile/features/auth/presentation/providers/auth_providers.dart';

class RegisterScreen extends ConsumerStatefulWidget {
  const RegisterScreen({super.key});

  @override
  ConsumerState<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends ConsumerState<RegisterScreen> {
  final _formKey = GlobalKey<FormState>();
  final _usernameController = TextEditingController();
  final _emailController = TextEditingController();
  final _phoneController = TextEditingController();
  final _passwordController = TextEditingController();
  final _confirmController = TextEditingController();
  bool _usePhone = false;

  @override
  void dispose() {
    _usernameController.dispose();
    _emailController.dispose();
    _phoneController.dispose();
    _passwordController.dispose();
    _confirmController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) {
      return;
    }

    final success = await ref.read(authNotifierProvider.notifier).register(
          username: _usernameController.text.trim(),
          email: _usePhone ? null : _emailController.text.trim(),
          phone: _usePhone ? _phoneController.text.trim() : null,
          password: _passwordController.text,
          passwordConfirmation: _confirmController.text,
        );

    if (!mounted) {
      return;
    }

    if (!success) {
      return;
    }

    final pendingPhone = ref.read(authNotifierProvider).pendingPhone;
    if (pendingPhone != null) {
      context.go('/otp', extra: pendingPhone);
    } else {
      context.go('/home');
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final auth = ref.watch(authNotifierProvider);

    return Scaffold(
      appBar: AppBar(title: Text(l10n.registerTitle)),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                SegmentedButton<bool>(
                  segments: [
                    ButtonSegment(value: false, label: Text(l10n.emailLabel)),
                    ButtonSegment(value: true, label: Text(l10n.phoneLabel)),
                  ],
                  selected: {_usePhone},
                  onSelectionChanged: (value) {
                    setState(() => _usePhone = value.first);
                  },
                ),
                const SizedBox(height: 16),
                TextFormField(
                  key: const Key('username_field'),
                  controller: _usernameController,
                  decoration: InputDecoration(labelText: l10n.usernameLabel),
                  validator: (value) =>
                      value == null || value.length < 3 ? l10n.authRequired : null,
                ),
                const SizedBox(height: 16),
                if (_usePhone)
                  TextFormField(
                    key: const Key('phone_field'),
                    controller: _phoneController,
                    decoration: InputDecoration(labelText: l10n.phoneLabel),
                    validator: (value) =>
                        value == null || value.isEmpty ? l10n.authRequired : null,
                  )
                else
                  TextFormField(
                    key: const Key('email_field'),
                    controller: _emailController,
                    decoration: InputDecoration(labelText: l10n.emailLabel),
                    validator: (value) =>
                        value == null || !value.contains('@') ? l10n.authRequired : null,
                  ),
                const SizedBox(height: 16),
                TextFormField(
                  key: const Key('password_field'),
                  controller: _passwordController,
                  obscureText: true,
                  decoration: InputDecoration(labelText: l10n.passwordLabel),
                  validator: (value) =>
                      value == null || value.length < 8 ? l10n.authRequired : null,
                ),
                const SizedBox(height: 16),
                TextFormField(
                  key: const Key('password_confirm_field'),
                  controller: _confirmController,
                  obscureText: true,
                  decoration: InputDecoration(labelText: l10n.passwordConfirmLabel),
                  validator: (value) => value != _passwordController.text
                      ? l10n.authRequired
                      : null,
                ),
                if (auth.error != null) ...[
                  const SizedBox(height: 16),
                  Text(auth.error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
                ],
                const SizedBox(height: 24),
                FilledButton(
                  onPressed: auth.isLoading ? null : _submit,
                  child: auth.isLoading
                      ? const SizedBox(
                          height: 20,
                          width: 20,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : Text(l10n.registerButton),
                ),
                const SizedBox(height: 16),
                TextButton(
                  onPressed: () => context.go('/login'),
                  child: Text('${l10n.hasAccount} ${l10n.goToLogin}'),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
