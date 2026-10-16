import 'package:flutter_test/flutter_test.dart';
import 'package:livecommerce_mobile/features/live/data/live_repository.dart';
import 'package:livecommerce_mobile/features/live/domain/entities/live_session.dart';
import 'package:livecommerce_mobile/features/live/presentation/providers/live_providers.dart';

class _Repository extends Fake implements LiveRepository {
  _Repository({this.publisherToken = 'publisher'});
  final String? publisherToken;
  int joins = 0;
  @override
  Future<LiveSession> get(String id) async => LiveSession(
    id: id, title: 'Live', status: 'live', publisherToken: publisherToken,
    subscriberToken: 'subscriber', seller: const LiveSellerBrief(id: 'owner', username: 'seller'),
  );
  @override
  Future<List<LiveChatMessage>> chat(String id, {int? afterId, int limit = 50}) async => [];
  @override
  Future<Map<String, int>> join(String id) async { joins++; return {}; }
}

void main() {
  test('opening a shared link on the host account still watches as a viewer', () async {
    final repository = _Repository();
    final room = LiveRoomNotifier(repository, 'room', 'owner', null);
    addTearDown(room.dispose);
    await room.enter();
    expect(room.state.isHost, false);
    expect(repository.joins, 1);
  });
  test('only explicit authorized host entry publishes', () async {
    final repository = _Repository();
    final room = LiveRoomNotifier(repository, 'room', 'owner', null);
    addTearDown(room.dispose);
    await room.enter(asHost: true);
    expect(room.state.isHost, true);
    expect(repository.joins, 0);
  });
  test('host URL cannot turn a viewer without publishing credentials into a host', () async {
    final repository = _Repository(publisherToken: null);
    final room = LiveRoomNotifier(repository, 'room', 'viewer', null);
    addTearDown(room.dispose);
    await room.enter(asHost: true);
    expect(room.state.isHost, false);
    expect(repository.joins, 1);
  });
}
