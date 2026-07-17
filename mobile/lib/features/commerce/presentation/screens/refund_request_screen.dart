import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:livecommerce_mobile/core/errors/error_handler.dart';
import 'package:livecommerce_mobile/core/l10n/app_localizations.dart';
import 'package:livecommerce_mobile/features/commerce/presentation/providers/commerce_providers.dart';

class RefundRequestScreen extends ConsumerStatefulWidget {
  const RefundRequestScreen({super.key, required this.orderId});

  final String orderId;

  @override
  ConsumerState<RefundRequestScreen> createState() => _RefundRequestScreenState();
}

class _RefundRequestScreenState extends ConsumerState<RefundRequestScreen> {
  final _reasonController = TextEditingController();
  bool _busy = false;

  @override
  void dispose() {
    _reasonController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final reason = _reasonController.text.trim();
    if (reason.length < 5) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(AppLocalizations.of(context)!.refundReasonTooShort)),
      );
      return;
    }

    setState(() => _busy = true);
    try {
      final order = await ref.read(commerceRepositoryProvider).requestRefund(
            orderId: widget.orderId,
            reason: reason,
          );
      if (!mounted) {
        return;
      }
      ref.invalidate(orderDetailProvider(widget.orderId));
      context.go('/orders/${order.id}');
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(AppLocalizations.of(context)!.refundRequested)),
      );
    } catch (error) {
      if (!mounted) {
        return;
      }
      setState(() => _busy = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(describeFailure(error))),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(AppLocalizations.of(context)!.refundTitle)),
      body: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              AppLocalizations.of(context)!.refundIntro,
              style: Theme.of(context).textTheme.bodyLarge,
            ),
            const SizedBox(height: 16),
            TextField(
              controller: _reasonController,
              maxLines: 5,
              decoration: InputDecoration(
                labelText: AppLocalizations.of(context)!.refundReasonLabel,
                border: const OutlineInputBorder(),
                alignLabelWithHint: true,
              ),
            ),
            const Spacer(),
            FilledButton(
              onPressed: _busy ? null : _submit,
              child: _busy
                  ? const SizedBox(
                      height: 20,
                      width: 20,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : Text(AppLocalizations.of(context)!.submitRefund),
            ),
          ],
        ),
      ),
    );
  }
}
