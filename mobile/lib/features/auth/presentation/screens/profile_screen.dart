import 'package:flutter/material.dart';
import 'package:livecommerce_mobile/core/l10n/app_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:livecommerce_mobile/features/auth/presentation/providers/auth_providers.dart';

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
      return Scaffold(
        body: Center(child: Text(l10n.authRequired)),
      );
    }

    return Scaffold(
      appBar: AppBar(title: Text(l10n.profileTitle)),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              CircleAvatar(
                radius: 40,
                child: Text(user.username.characters.first.toUpperCase()),
              ),
              const SizedBox(height: 8),
              Text('@${user.username}', textAlign: TextAlign.center),
              const SizedBox(height: 24),
              TextFormField(
                key: const Key('display_name_field'),
                controller: _displayNameController,
                decoration: InputDecoration(labelText: l10n.displayNameLabel),
              ),
              const SizedBox(height: 16),
              TextFormField(
                key: const Key('bio_field'),
                controller: _bioController,
                maxLines: 3,
                decoration: InputDecoration(labelText: l10n.bioLabel),
              ),
              const SizedBox(height: 24),
              FilledButton(
                onPressed: auth.isLoading ? null : _save,
                child: Text(l10n.saveButton),
              ),
              const SizedBox(height: 12),
              OutlinedButton(
                onPressed: () => context.push('/orders'),
                child: const Text('My orders'),
              ),
              const SizedBox(height: 12),
              OutlinedButton(
                onPressed: () => context.push(
                  user.isSeller ? '/seller' : '/seller/apply',
                ),
                child: Text(user.isSeller ? 'Seller center' : 'Become a seller'),
              ),
              const SizedBox(height: 12),
              OutlinedButton(
                onPressed: () => context.push('/settings'),
                child: Text(l10n.settingsTitle),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
