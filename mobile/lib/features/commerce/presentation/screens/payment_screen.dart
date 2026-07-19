import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:livecommerce_mobile/core/errors/error_handler.dart';
import 'package:livecommerce_mobile/core/l10n/app_localizations.dart';
import 'package:livecommerce_mobile/features/commerce/domain/entities/order.dart';
import 'package:livecommerce_mobile/features/commerce/presentation/providers/commerce_providers.dart';
import 'package:url_launcher/url_launcher.dart';

/// Payment step: sandbox Pay/Cancel, or open real gateway URL + poll order status.
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

  bool get _isSandboxUrl {
    final url = widget.paymentUrl;
    if (url == null || url.isEmpty) {
      return true;
    }
    return url.contains('/payments/sandbox/');
  }

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

  Future<void> _openGateway() async {
    final raw = widget.paymentUrl;
    if (raw == null || raw.isEmpty) {
      return;
    }
    final uri = Uri.tryParse(raw);
    if (uri == null) {
      setState(() => _error = 'Invalid payment URL');
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final ok = await launchUrl(uri, mode: LaunchMode.externalApplication);
      if (!ok && mounted) {
        setState(() => _error = 'Could not open payment page');
      }
    } catch (error) {
      if (mounted) {
        setState(() => _error = describeFailure(error));
      }
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  Future<void> _refreshStatus() async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final order =
          await ref.read(commerceRepositoryProvider).getOrder(widget.orderId);
      if (!mounted) {
        return;
      }
      setState(() {
        _order = order;
        _busy = false;
      });
      if (!order.isAwaitingPayment) {
        context.go('/order-success/${order.id}', extra: order);
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
                    _isSandboxUrl
                        ? l10n.paymentSandboxHint
                        : 'Open the payment page, then tap Refresh after paying.',
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
                  if (_isSandboxUrl) ...[
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
                  ] else ...[
                    FilledButton(
                      onPressed: _busy ? null : _openGateway,
                      child: Text(l10n.payNow),
                    ),
                    const SizedBox(height: 12),
                    OutlinedButton(
                      onPressed: _busy ? null : _refreshStatus,
                      child: Text(l10n.refresh),
                    ),
                  ],
                ],
              ),
            ),
    );
  }
}
