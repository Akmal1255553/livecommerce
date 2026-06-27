import 'package:flutter_test/flutter_test.dart';
import 'package:livecommerce_mobile/app/app.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

void main() {
  testWidgets('App renders home screen', (tester) async {
    await tester.pumpWidget(
      const ProviderScope(
        child: LiveCommerceApp(),
      ),
    );

    expect(find.text('LiveCommerce'), findsOneWidget);
    expect(find.text('Sprint 0.3 — Project Skeleton'), findsOneWidget);
  });
}
