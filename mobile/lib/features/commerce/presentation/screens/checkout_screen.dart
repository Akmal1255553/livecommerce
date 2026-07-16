import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
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
        const SnackBar(content: Text('Cart is empty')),
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

    final order = await ref.read(checkoutNotifierProvider.notifier).submit(
          cartVersion: cart.version,
          address: address,
        );

    if (!mounted) {
      return;
    }

    if (order != null) {
      await ref.read(cartNotifierProvider.notifier).load();
      if (!mounted) {
        return;
      }
      context.go('/order-success/${order.id}', extra: order);
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
      appBar: AppBar(title: const Text('Checkout')),
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
                              'Order summary',
                              style: Theme.of(context).textTheme.titleMedium,
                            ),
                            const SizedBox(height: 8),
                            Text('${cart.summary.itemCount} items'),
                            Text('Subtotal: ${cart.summary.subtotal.format()}'),
                            Text(
                              'Shipping est.: ${cart.summary.shippingEstimate.format()}',
                            ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: 16),
                  ],
                  Text(
                    'Shipping address',
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _fullNameController,
                    decoration: const InputDecoration(labelText: 'Full name'),
                    textInputAction: TextInputAction.next,
                    validator: (v) =>
                        v == null || v.trim().isEmpty ? 'Required' : null,
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _phoneController,
                    decoration: const InputDecoration(labelText: 'Phone'),
                    keyboardType: TextInputType.phone,
                    textInputAction: TextInputAction.next,
                    validator: (v) =>
                        v == null || v.trim().isEmpty ? 'Required' : null,
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _regionController,
                    decoration: const InputDecoration(labelText: 'Region'),
                    textInputAction: TextInputAction.next,
                    validator: (v) =>
                        v == null || v.trim().isEmpty ? 'Required' : null,
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _cityController,
                    decoration: const InputDecoration(labelText: 'City'),
                    textInputAction: TextInputAction.next,
                    validator: (v) =>
                        v == null || v.trim().isEmpty ? 'Required' : null,
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _addressController,
                    decoration: const InputDecoration(labelText: 'Address'),
                    textInputAction: TextInputAction.next,
                    validator: (v) =>
                        v == null || v.trim().isEmpty ? 'Required' : null,
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _postalCodeController,
                    decoration: const InputDecoration(labelText: 'Postal code'),
                    textInputAction: TextInputAction.done,
                    validator: (v) =>
                        v == null || v.trim().isEmpty ? 'Required' : null,
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
                        : const Text('Place order'),
                  ),
                ],
              ),
            ),
    );
  }
}
