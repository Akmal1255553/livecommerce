import 'package:flutter_test/flutter_test.dart';
import 'package:livecommerce_mobile/core/analytics/analytics_session.dart';
import 'package:livecommerce_mobile/core/constants/storage_keys.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  test('AnalyticsSession creates and reuses a UUID session id', () async {
    SharedPreferences.setMockInitialValues({});
    final prefs = await SharedPreferences.getInstance();
    final session = AnalyticsSession(prefs);

    final first = await session.id();
    final second = await session.id();

    expect(
      first,
      matches(
        RegExp(
          r'^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$',
        ),
      ),
    );
    expect(second, first);
    expect(prefs.getString(StorageKeys.analyticsSessionId), first);
  });

  test('AnalyticsSession restores persisted id', () async {
    const existing = '11111111-2222-4333-8444-555555555555';
    SharedPreferences.setMockInitialValues({
      StorageKeys.analyticsSessionId: existing,
    });
    final prefs = await SharedPreferences.getInstance();
    final session = AnalyticsSession(prefs);

    expect(await session.id(), existing);
  });
}
