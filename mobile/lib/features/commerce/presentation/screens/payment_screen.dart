import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:livecommerce_mobile/core/errors/error_handler.dart';
import 'package:livecommerce_mobile/core/l10n/app_localizations.dart';
import 'package:livecommerce_mobile/features/commerce/domain/entities/order.dart';
import 'package:livecommerce_mobile/features/commerce/presentation/providers/commerce_providers.dart';

/// Sandbox / local gateway payment step (Sprint 7).
/// Completes via `POST /payments/sandbox/{id}/complete` until a real Click/Payme SDK is wired.
class PaymentScreen extends ConsumerStatefulWidget {
  const PaymentScreen({
    super.key,
    required this.orderId,
    this.initialOrder,
    this.paymentUrl,
  });

  final String orderId;
  final Order? initialOrder;
  final String? paymentUrl;

  @override
  ConsumerState<PaymentScreen> createState() => _PaymentScreenState();
}

class _PaymentScreenState extends ConsumerState<PaymentScreen> {
  bool _busy = false;
  String? _error;
  Order? _order;

  @override
  void initState() {
    super.initState();
    _order = widget.initialOrder;
    if (_order == null) {
      Future.microtask(_load);
    }
  }

  Future<void> _load() async {
    try {
      final order =
          await ref.read(commerceRepositoryProvider).getOrder(widget.orderId);
      if (!mounted) {
        return;
      }
      setState(() => _order = order);
      if (!order.isAwaitingPayment) {
        context.go('/order-success/${order.id}', extra: order);
      }
    } catch (error) {
      if (!mounted) {
        return;
      }
      setState(() => _error = describeFailure(error));
    }
  }

  Future<void> _complete(String result) async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final order = await ref.read(commerceRepositoryProvider).completeSandboxPayment(
            orderId: widget.orderId,
            result: result,
          );
      if (!mounted) {
        return;
      }
      if (result == 'success') {
        context.go('/order-success/${order.id}', extra: order);
      } else {
        context.go('/orders/${order.id}');
      }
    } catch (error) {
      if (!mounted) {
        return;
      }
      setState(() {
        _busy = false;
        _error = describeFailure(error);
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final order = _order;
    final theme = Theme.of(context);
    final l10n = AppLocalizations.of(context)!;

    return Scaffold(
      appBar: AppBar(title: Text(AppLocalizations.of(context)!.paymentTitle)),
      body: order == null
          ? Center(
              child: _error != null
                  ? Padding(
                      padding: const EdgeInsets.all(24),
                      child: Text(_error!, textAlign: TextAlign.center),
                    )
                  : const CircularProgressIndicator(),
            )
          : Padding(
              padding: const EdgeInsets.all(24),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text(
                    l10n.payForOrder(order.orderNumber),
                    style: theme.textTheme.headlineSmall?.copyWith(
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 12),
                  Text(
                    l10n.amountLabel(order.totals.total.format()),
                    style: theme.textTheme.titleLarge,
                  ),
                  const SizedBox(height: 8),
                  Text(
                    order.paymentProvider != null
                        ? '${l10n.methodWithValue(order.paymentMethod ?? 'click')} · ${order.paymentProvider}'
                        : l10n.methodWithValue(order.paymentMethod ?? 'click'),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    order.paymentStatus != null
                        ? '${l10n.orderStatusLabel(order.status)} · ${l10n.orderPaymentLabel(order.paymentStatus!)}'
                        : l10n.orderStatusLabel(order.status),
                  ),
                  if (widget.paymentUrl != null) ...[
                    const SizedBox(height: 16),
                    Text(
                      l10n.paymentRedirectLabel(widget.paymentUrl!),
                      style: theme.textTheme.bodySmall,
                    ),
                  ],
                  const SizedBox(height: 24),
                  Text(
                    l10n.paymentSandboxHint,
                    style: theme.textTheme.bodyMedium,
                  ),
                  if (_error != null) ...[
                    const SizedBox(height: 16),
                    Text(
                      _error!,
                      style: TextStyle(color: theme.colorScheme.error),
                    ),
                  ],
                  const Spacer(),
                  FilledButton(
                    onPressed: _busy ? null : () => _complete('success'),
                    child: _busy
                        ? const SizedBox(
                            height: 20,
                            width: 20,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : Text(l10n.payNow),
                  ),
                  const SizedBox(height: 12),
                  OutlinedButton(
                    onPressed: _busy ? null : () => _complete('failed'),
                    child: Text(l10n.cancelPayment),
                  ),
                ],
              ),
            ),
    );
  }
}
