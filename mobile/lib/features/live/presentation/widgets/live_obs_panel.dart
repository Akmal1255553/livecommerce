import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:livecommerce_mobile/core/l10n/app_localizations.dart';
import 'package:livecommerce_mobile/features/live/domain/entities/live_session.dart';

/// Host view for RTMP (OBS) sessions. The host streams from OBS using the
/// shown server + stream key; this panel mirrors the stream state which the
/// room poll refreshes from `GET /live/{id}`.
class LiveObsHostPanel extends StatelessWidget {
  const LiveObsHostPanel({super.key, required this.session});

  final LiveSession session;

  void _copy(BuildContext context, String value, String copiedLabel) {
    Clipboard.setData(ClipboardData(text: value));
    if (!context.mounted) {
      return;
    }
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(copiedLabel), duration: const Duration(seconds: 1)),
    );
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final isStreaming = session.isStreaming ?? false;
    final server = session.rtmpUrl ?? '';
    final key = session.streamKey ?? '';

    return Container(
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [Color(0xFF1A1A2E), Color(0xFF16213E), Color(0xFF0F3460)],
        ),
      ),
      padding: const EdgeInsets.all(20),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(
                isStreaming ? Icons.cast_connected : Icons.radio_button_checked,
                color: isStreaming ? Colors.greenAccent : Colors.amberAccent,
              ),
              const SizedBox(width: 8),
              Text(
                isStreaming ? l10n.obsLive : l10n.obsWaiting,
                style: TextStyle(
                  color: isStreaming ? Colors.greenAccent : Colors.amberAccent,
                  fontWeight: FontWeight.w700,
                  fontSize: 15,
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          Text(
            l10n.obsPanelTitle,
            style: const TextStyle(
              color: Colors.white,
              fontWeight: FontWeight.w800,
              fontSize: 17,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            l10n.obsHint,
            style: const TextStyle(color: Colors.white54, fontSize: 12),
          ),
          const SizedBox(height: 16),
          _CredentialRow(
            label: l10n.obsServerLabel,
            value: server,
            onCopy: () => _copy(context, server, l10n.obsCopied),
          ),
          const SizedBox(height: 12),
          _CredentialRow(
            label: l10n.obsStreamKeyLabel,
            value: key,
            monospace: true,
            onCopy: () => _copy(context, key, l10n.obsCopied),
          ),
        ],
      ),
    );
  }
}

class _CredentialRow extends StatelessWidget {
  const _CredentialRow({
    required this.label,
    required this.value,
    required this.onCopy,
    this.monospace = false,
  });

  final String label;
  final String value;
  final VoidCallback onCopy;
  final bool monospace;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.06),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: Colors.white.withValues(alpha: 0.12)),
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  label,
                  style: const TextStyle(color: Colors.white38, fontSize: 11),
                ),
                const SizedBox(height: 4),
                SelectableText(
                  value,
                  style: TextStyle(
                    color: Colors.white,
                    fontFamily: monospace ? 'monospace' : null,
                    fontSize: 13,
                  ),
                ),
              ],
            ),
          ),
          IconButton(
            onPressed: onCopy,
            icon: const Icon(Icons.copy_rounded, color: Colors.white70, size: 20),
            tooltip: label,
          ),
        ],
      ),
    );
  }
}
