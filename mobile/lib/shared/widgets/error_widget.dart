import 'package:flutter/material.dart';

class ErrorDisplay extends StatelessWidget {
  const ErrorDisplay({
    super.key,
    required this.message,
    this.onRetry,
    this.foregroundColor,
  });

  final String message;
  final VoidCallback? onRetry;
  final Color? foregroundColor;

  @override
  Widget build(BuildContext context) {
    final fg = foregroundColor ?? Theme.of(context).colorScheme.onSurface;
    final err = foregroundColor ?? Theme.of(context).colorScheme.error;

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.error_outline, size: 48, color: err),
            const SizedBox(height: 12),
            Text(message, textAlign: TextAlign.center, style: TextStyle(color: fg)),
            if (onRetry != null) ...[
              const SizedBox(height: 16),
              FilledButton(onPressed: onRetry, child: const Text('Retry')),
            ],
          ],
        ),
      ),
    );
  }
}
