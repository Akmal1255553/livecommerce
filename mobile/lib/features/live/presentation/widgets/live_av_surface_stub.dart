import 'package:flutter/material.dart';
import 'package:livecommerce_mobile/core/l10n/app_localizations.dart';
import 'package:livecommerce_mobile/features/live/domain/entities/live_session.dart';

Widget buildLiveAvSurface({
  required LiveSession session,
  required bool isHost,
}) {
  return const _LiveAvPlaceholder();
}

class _LiveAvPlaceholder extends StatelessWidget {
  const _LiveAvPlaceholder();

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    return Container(
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [Color(0xFF1A1A2E), Color(0xFF16213E), Color(0xFF0F3460)],
        ),
      ),
      child: Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.videocam, color: Colors.white54, size: 64),
            const SizedBox(height: 12),
            Text(
              l10n.livePlaceholderTitle,
              style: const TextStyle(color: Colors.white70, fontSize: 16),
            ),
            const SizedBox(height: 4),
            Text(
              l10n.livePlaceholderBody,
              style: const TextStyle(color: Colors.white38, fontSize: 13),
              textAlign: TextAlign.center,
            ),
          ],
        ),
      ),
    );
  }
}
