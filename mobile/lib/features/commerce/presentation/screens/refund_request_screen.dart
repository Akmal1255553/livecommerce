import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:livecommerce_mobile/core/errors/error_handler.dart';
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
        const SnackBar(content: Text('Please describe the reason (min 5 characters)')),
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
        const SnackBar(content: Text('Refund requested')),
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
      appBar: AppBar(title: const Text('Request refund')),
      body: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              'Tell the seller why you need a refund.',
              style: Theme.of(context).textTheme.bodyLarge,
            ),
            const SizedBox(height: 16),
            TextField(
              controller: _reasonController,
              maxLines: 5,
              decoration: const InputDecoration(
                labelText: 'Reason',
                border: OutlineInputBorder(),
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
                  : const Text('Submit refund request'),
            ),
          ],
        ),
      ),
    );
  }
}
