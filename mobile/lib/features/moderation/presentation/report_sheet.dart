import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:livecommerce_mobile/core/errors/error_handler.dart';
import 'package:livecommerce_mobile/core/l10n/app_localizations.dart';
import 'package:livecommerce_mobile/features/auth/presentation/providers/auth_providers.dart';

final reportRepositoryProvider = Provider<ReportRepository>((ref) {
  return ReportRepository(ref.watch(authDioProvider));
});

class ReportRepository {
  ReportRepository(this._dio);

  final Dio _dio;

  Future<void> submit({
    required String targetType,
    required String targetId,
    required String reason,
  }) async {
    await _dio.post<Map<String, dynamic>>(
      '/reports',
      data: {
        'target_type': targetType,
        'target_id': targetId,
        'reason': reason,
      },
    );
  }
}

Future<void> showReportSheet({
  required BuildContext context,
  required WidgetRef ref,
  required String targetType,
  required String targetId,
}) async {
  final l10n = AppLocalizations.of(context)!;
  final reasons = [
    l10n.reportReasonSpam,
    l10n.reportReasonInappropriate,
    l10n.reportReasonScam,
    l10n.reportReasonOther,
  ];

  final selected = await showModalBottomSheet<String>(
    context: context,
    showDragHandle: true,
    builder: (context) {
      return SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
              child: Text(
                l10n.reportTitle,
                style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w700,
                    ),
              ),
            ),
            ...reasons.map(
              (reason) => ListTile(
                title: Text(reason),
                onTap: () => Navigator.of(context).pop(reason),
              ),
            ),
            const SizedBox(height: 8),
          ],
        ),
      );
    },
  );

  if (selected == null || !context.mounted) {
    return;
  }

  try {
    await ref.read(reportRepositoryProvider).submit(
          targetType: targetType,
          targetId: targetId,
          reason: selected,
        );
    if (context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(l10n.reportSubmitted)),
      );
    }
  } catch (error) {
    if (context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(describeFailure(error))),
      );
    }
  }
}
