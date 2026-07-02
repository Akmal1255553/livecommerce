import 'package:flutter/material.dart';
import 'package:livecommerce_mobile/core/l10n/app_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:livecommerce_mobile/features/auth/presentation/providers/auth_providers.dart';

class SettingsScreen extends ConsumerWidget {
  const SettingsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context)!;
    final settings = ref.watch(appSettingsProvider);
    final settingsNotifier = ref.read(appSettingsProvider.notifier);

    return Scaffold(
      appBar: AppBar(title: Text(l10n.settingsTitle)),
      body: ListView(
        children: [
          ListTile(
            title: Text(l10n.localeLabel),
            subtitle: Text(settings.locale.languageCode.toUpperCase()),
          ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: SegmentedButton<String>(
              segments: const [
                ButtonSegment(value: 'uz', label: Text('UZ')),
                ButtonSegment(value: 'ru', label: Text('RU')),
              ],
              selected: {settings.locale.languageCode},
              onSelectionChanged: (value) {
                settingsNotifier.setLocale(Locale(value.first));
              },
            ),
          ),
          const Divider(),
          SwitchListTile(
            title: Text(l10n.darkModeLabel),
            value: settings.themeMode == ThemeMode.dark,
            onChanged: (enabled) {
              settingsNotifier.setThemeMode(
                enabled ? ThemeMode.dark : ThemeMode.light,
              );
            },
          ),
          ListTile(
            title: Text(l10n.systemTheme),
            trailing: settings.themeMode == ThemeMode.system
                ? const Icon(Icons.check)
                : null,
            onTap: () => settingsNotifier.setThemeMode(ThemeMode.system),
          ),
          const Divider(),
          ListTile(
            title: Text(l10n.logoutButton),
            leading: const Icon(Icons.logout),
            onTap: () async {
              await ref.read(authNotifierProvider.notifier).logout();
              if (context.mounted) {
                context.go('/login');
              }
            },
          ),
        ],
      ),
    );
  }
}
