import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:livecommerce_mobile/core/l10n/app_localizations.dart';
import 'package:livecommerce_mobile/features/commerce/domain/entities/order.dart';
import 'package:livecommerce_mobile/features/commerce/presentation/providers/commerce_providers.dart';

class CheckoutScreen extends ConsumerStatefulWidget {
  const CheckoutScreen({super.key});

  @override
  ConsumerState<CheckoutScreen> createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends ConsumerState<CheckoutScreen> {
  final _formKey = GlobalKey<FormState>();
  final _fullNameController = TextEditingController();
  final _phoneController = TextEditingController();
  final _regionController = TextEditingController();
  final _cityController = TextEditingController();
  final _addressController = TextEditingController();
  final _postalCodeController = TextEditingController();
  String _paymentMethod = 'click';

  @override
  void initState() {
    super.initState();
    Future.microtask(() => ref.read(cartNotifierProvider.notifier).load());
    ref.read(checkoutNotifierProvider.notifier).reset();
  }

  @override
  void dispose() {
    _fullNameController.dispose();
    _phoneController.dispose();
    _regionController.dispose();
    _cityController.dispose();
    _addressController.dispose();
    _postalCodeController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) {
      return;
    }

    await ref.read(cartNotifierProvider.notifier).load();
    if (!mounted) {
      return;
    }

    final cart = ref.read(cartNotifierProvider).cart;
    if (cart == null || cart.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(AppLocalizations.of(context)!.cartIsEmpty)),
      );
      return;
    }

    final address = ShippingAddress(
      fullName: _fullNameController.text.trim(),
      phone: _phoneController.text.trim(),
      region: _regionController.text.trim(),
      city: _cityController.text.trim(),
      addressLine: _addressController.text.trim(),
      postalCode: _postalCodeController.text.trim(),
    );

    final result = await ref.read(checkoutNotifierProvider.notifier).submit(
          cartVersion: cart.version,
          address: address,
          paymentMethod: _paymentMethod,
        );

    if (!mounted) {
      return;
    }

    if (result != null) {
      await ref.read(cartNotifierProvider.notifier).load();
      if (!mounted) {
        return;
      }

      final order = result.order;
      if (order.isAwaitingPayment || result.paymentUrl != null) {
        context.go(
          '/payment/${order.id}',
          extra: {
            'order': order,
            'paymentUrl': result.paymentUrl,
          },
        );
      } else {
        context.go('/order-success/${order.id}', extra: order);
      }
    } else {
      final error = ref.read(checkoutNotifierProvider).error;
      if (error != null) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error)));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final cartState = ref.watch(cartNotifierProvider);
    final checkout = ref.watch(checkoutNotifierProvider);
    final cart = cartState.cart;

    return Scaffold(
      appBar: AppBar(title: Text(AppLocalizations.of(context)!.checkoutTitle)),
      body: cartState.isLoading && cart == null
          ? const Center(child: CircularProgressIndicator())
          : Form(
              key: _formKey,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  if (cart != null) ...[
                    Card(
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              AppLocalizations.of(context)!.orderSummary,
                              style: Theme.of(context).textTheme.titleMedium,
                            ),
                            const SizedBox(height: 8),
                            Text(AppLocalizations.of(context)!.itemCountLabel(cart.summary.itemCount)),
                            Text(AppLocalizations.of(context)!.subtotalWithAmount(cart.summary.subtotal.format())),
                            Text(
                              AppLocalizations.of(context)!.shippingEstimate(cart.summary.shippingEstimate.format()),
                            ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: 16),
                  ],
                  Text(
                    AppLocalizations.of(context)!.paymentMethod,
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                  const SizedBox(height: 8),
                  SegmentedButton<String>(
                    segments: const [
                      ButtonSegment(value: 'click', label: Text('Click')),
                      ButtonSegment(value: 'payme', label: Text('Payme')),
                      ButtonSegment(value: 'uzum', label: Text('Uzum')),
                    ],
                    selected: {_paymentMethod},
                    onSelectionChanged: (value) {
                      setState(() => _paymentMethod = value.first);
                    },
                  ),
                  const SizedBox(height: 16),
                  Text(
                    AppLocalizations.of(context)!.shippingAddress,
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _fullNameController,
                    decoration: InputDecoration(labelText: AppLocalizations.of(context)!.fullNameLabel),
                    textInputAction: TextInputAction.next,
                    validator: (v) =>
                        v == null || v.trim().isEmpty ? AppLocalizations.of(context)!.requiredField : null,
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _phoneController,
                    decoration: InputDecoration(labelText: AppLocalizations.of(context)!.phoneLabel),
                    keyboardType: TextInputType.phone,
                    textInputAction: TextInputAction.next,
                    validator: (v) =>
                        v == null || v.trim().isEmpty ? AppLocalizations.of(context)!.requiredField : null,
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _regionController,
                    decoration: InputDecoration(labelText: AppLocalizations.of(context)!.regionLabel),
                    textInputAction: TextInputAction.next,
                    validator: (v) =>
                        v == null || v.trim().isEmpty ? AppLocalizations.of(context)!.requiredField : null,
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _cityController,
                    decoration: InputDecoration(labelText: AppLocalizations.of(context)!.cityLabel),
                    textInputAction: TextInputAction.next,
                    validator: (v) =>
                        v == null || v.trim().isEmpty ? AppLocalizations.of(context)!.requiredField : null,
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _addressController,
                    decoration: InputDecoration(labelText: AppLocalizations.of(context)!.addressLabel),
                    textInputAction: TextInputAction.next,
                    validator: (v) =>
                        v == null || v.trim().isEmpty ? AppLocalizations.of(context)!.requiredField : null,
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _postalCodeController,
                    decoration: InputDecoration(labelText: AppLocalizations.of(context)!.postalCodeLabel),
                    textInputAction: TextInputAction.done,
                    validator: (v) =>
                        v == null || v.trim().isEmpty ? AppLocalizations.of(context)!.requiredField : null,
                  ),
                  const SizedBox(height: 24),
                  FilledButton(
                    onPressed: checkout.isSubmitting ? null : _submit,
                    child: checkout.isSubmitting
                        ? const SizedBox(
                            height: 20,
                            width: 20,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : Text(AppLocalizations.of(context)!.placeOrder),
                  ),
                ],
              ),
            ),
    );
  }
}
