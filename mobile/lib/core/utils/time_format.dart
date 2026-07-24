import 'package:livecommerce_mobile/core/l10n/app_localizations.dart';

/// Compact relative time for feed/chat metadata: `сейчас`, `5 мин`, `3 дн`.
String formatTimeAgo(AppLocalizations l10n, String? iso) {
  if (iso == null || iso.isEmpty) {
    return '';
  }
  final parsed = DateTime.tryParse(iso);
  if (parsed == null) {
    return '';
  }

  final diff = DateTime.now().difference(parsed.toLocal());
  if (diff.inSeconds < 60) {
    return l10n.timeJustNow;
  }
  if (diff.inMinutes < 60) {
    return l10n.timeMinutesShort(diff.inMinutes);
  }
  if (diff.inHours < 24) {
    return l10n.timeHoursShort(diff.inHours);
  }
  if (diff.inDays < 7) {
    return l10n.timeDaysShort(diff.inDays);
  }
  return l10n.timeWeeksShort(diff.inDays ~/ 7);
}
