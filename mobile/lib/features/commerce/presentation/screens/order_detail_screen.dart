import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:livecommerce_mobile/core/l10n/app_localizations.dart';
import 'package:livecommerce_mobile/features/commerce/domain/entities/money_amount.dart';
import 'package:livecommerce_mobile/features/commerce/domain/entities/order.dart';
import 'package:livecommerce_mobile/features/commerce/presentation/providers/commerce_providers.dart';
import 'package:livecommerce_mobile/features/messaging/presentation/providers/messaging_providers.dart';
import 'package:livecommerce_mobile/shared/widgets/error_widget.dart';
import 'package:livecommerce_mobile/shared/widgets/skeleton.dart';

class OrderDetailScreen extends ConsumerStatefulWidget {
  const OrderDetailScreen({super.key, required this.orderId});

  final String orderId;

  @override
  ConsumerState<OrderDetailScreen> createState() => _OrderDetailScreenState();
}

class _OrderDetailScreenState extends ConsumerState<OrderDetailScreen> {
  bool _isMessaging = false;

  Future<void> _messageAboutOrder(Order order) async {
    if (order.sellerUserId == null) return;
    setState(() => _isMessaging = true);
    try {
      final conv = await ref
          .read(conversationsNotifierProvider.notifier)
          .createConversation(
            sellerId: order.sellerUserId!,
            orderId: order.id,
          );
      if (mounted) {
        context.push('/conversations/${conv.id}', extra: conv);
      }
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content:
                Text(AppLocalizations.of(context)!.sendMessageFailed),
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _isMessaging = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final orderAsync = ref.watch(orderDetailProvider(widget.orderId));

    return Scaffold(
      appBar: AppBar(
        title: Text(AppLocalizations.of(context)!.orderDetailsTitle),
      ),
      body: orderAsync.when(
        loading: () => const ListSkeleton(itemCount: 4),
        error: (error, _) => ErrorDisplay(
          message: error.toString(),
          onRetry: () => ref.invalidate(orderDetailProvider(widget.orderId)),
        ),
        data: (order) => _OrderDetailBody(
          order: order,
          isMessaging: _isMessaging,
          onMessageAboutOrder: order.sellerUserId != null
              ? () => _messageAboutOrder(order)
              : null,
        ),
      ),
    );
  }
}

class _OrderDetailBody extends StatelessWidget {
  const _OrderDetailBody({
    required this.order,
    required this.isMessaging,
    this.onMessageAboutOrder,
  });

  final Order order;
  final bool isMessaging;
  final VoidCallback? onMessageAboutOrder;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final l10n = AppLocalizations.of(context)!;

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Text(
          '#${order.orderNumber}',
          style: theme.textTheme.headlineSmall?.copyWith(
            fontWeight: FontWeight.w700,
          ),
        ),
        const SizedBox(height: 4),
        Text(l10n.orderStatusLabel(order.status)),
        if (order.paymentStatus != null)
          Text(l10n.orderPaymentLabel(order.paymentStatus!)),
        if (order.paymentMethod != null)
          Text(l10n.orderMethodLabel(order.paymentMethod!)),
        if (order.createdAt != null) Text(l10n.orderPlacedLabel(order.createdAt!.toString())),
        if (order.isAwaitingPayment) ...[
          const SizedBox(height: 16),
          FilledButton(
            onPressed: () => context.push('/payment/${order.id}', extra: {
              'order': order,
            }),
            child: Text(l10n.continuePayment),
          ),
        ],
        if (order.canRequestRefund) ...[
          const SizedBox(height: 12),
          OutlinedButton(
            onPressed: () => context.push('/orders/${order.id}/refund'),
            child: Text(l10n.requestRefund),
          ),
        ],
        if (onMessageAboutOrder != null) ...[
          const SizedBox(height: 12),
          OutlinedButton(
            onPressed: isMessaging ? null : onMessageAboutOrder,
            child: isMessaging
                ? const SizedBox(
                    height: 18,
                    width: 18,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : Text(l10n.messageAboutOrder),
          ),
        ],
        const SizedBox(height: 20),
        Text(l10n.orderItems, style: theme.textTheme.titleMedium),
        const SizedBox(height: 8),
        ...order.items.map(
          (item) => ListTile(
            contentPadding: EdgeInsets.zero,
            title: Text(item.productTitle),
            subtitle: Text(
              '${item.quantity} × ${item.unitPrice.format()}'
              '${item.variantName != null ? ' · ${item.variantName}' : ''}',
            ),
            trailing: Text(item.lineTotal.format()),
          ),
        ),
        const Divider(height: 32),
        _TotalRow(label: l10n.subtotal, amount: order.totals.subtotal),
        _TotalRow(label: l10n.shipping, amount: order.totals.shipping),
        _TotalRow(label: l10n.discount, amount: order.totals.discount),
        _TotalRow(label: l10n.tax, amount: order.totals.tax),
        const SizedBox(height: 8),
        _TotalRow(
          label: l10n.total,
          amount: order.totals.total,
          emphasized: true,
        ),
        if (order.shippingAddress != null) ...[
          const Divider(height: 32),
          Text(l10n.shippingAddress, style: theme.textTheme.titleMedium),
          const SizedBox(height: 8),
          Text(order.shippingAddress!.fullName),
          Text(order.shippingAddress!.phone),
          Text(
            '${order.shippingAddress!.addressLine}, '
            '${order.shippingAddress!.city}, '
            '${order.shippingAddress!.region} '
            '${order.shippingAddress!.postalCode}',
          ),
        ],
      ],
    );
  }
}

class _TotalRow extends StatelessWidget {
  const _TotalRow({
    required this.label,
    required this.amount,
    this.emphasized = false,
  });

  final String label;
  final MoneyAmount amount;
  final bool emphasized;

  @override
  Widget build(BuildContext context) {
    final style = emphasized
        ? Theme.of(context).textTheme.titleMedium?.copyWith(
              fontWeight: FontWeight.w700,
            )
        : Theme.of(context).textTheme.bodyMedium;

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 2),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: style),
          Text(amount.format(), style: style),
        ],
      ),
    );
  }
}
