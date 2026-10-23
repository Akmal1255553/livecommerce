import 'package:flutter_test/flutter_test.dart';
import 'package:livecommerce_mobile/features/live/data/live_repository.dart';
import 'package:livecommerce_mobile/features/live/domain/entities/live_session.dart';
import 'package:livecommerce_mobile/features/live/presentation/providers/live_providers.dart';

class _Repository extends Fake implements LiveRepository {
  _Repository({this.publisherToken = 'publisher', this.status = 'live'});
  final String? publisherToken;
  String status;
  int joins = 0;
  int leaves = 0;
  int chats = 0;
  int gets = 0;
  @override
  Future<LiveSession> get(String id) async {
    gets++;
    return LiveSession(
      id: id,
      title: 'Live',
      status: status,
      publisherToken: publisherToken,
      subscriberToken: 'subscriber',
      seller: const LiveSellerBrief(id: 'owner', username: 'seller'),
    );
  }

  @override
  Future<List<LiveChatMessage>> chat(
    String id, {
    int? afterId,
    int limit = 50,
  }) async {
    chats++;
    return [];
  }

  @override
  Future<Map<String, int>> join(String id) async {
    joins++;
    return {};
  }

  @override
  Future<Map<String, int>> leave(String id) async {
    leaves++;
    return {};
  }
}

void main() {
  test(
    'ended links display the session without joining or requesting chat',
    () async {
      final repository = _Repository(status: 'ended');
      final room = LiveRoomNotifier(repository, 'room', 'owner', null);
      addTearDown(room.dispose);
      await room.enter(asHost: true);
      expect(room.state.session?.isLive, false);
      expect(room.state.isHost, false);
      expect(room.state.isLoading, false);
      expect(room.state.error, isNull);
      expect(repository.joins, 0);
      expect(repository.chats, 0);
    },
  );
  testWidgets(
    'polling detects the end and leaves once instead of keeping an empty room',
    (tester) async {
      final repository = _Repository();
      final room = LiveRoomNotifier(repository, 'room', 'viewer', null);
      addTearDown(room.dispose);
      await room.enter();
      repository.status = 'ended';
      await tester.pump(const Duration(seconds: 5));
      await tester.pump();
      expect(room.state.session?.isLive, false);
      expect(repository.leaves, 1);
      final getsAtEnd = repository.gets;
      await tester.pump(const Duration(seconds: 20));
      expect(repository.gets, getsAtEnd);
      expect(repository.leaves, 1);
    },
  );
  test(
    'opening a shared link on the host account still watches as a viewer',
    () async {
      final repository = _Repository();
      final room = LiveRoomNotifier(repository, 'room', 'owner', null);
      addTearDown(room.dispose);
      await room.enter();
      expect(room.state.isHost, false);
      expect(repository.joins, 1);
    },
  );
  test('only explicit authorized host entry publishes', () async {
    final repository = _Repository();
    final room = LiveRoomNotifier(repository, 'room', 'owner', null);
    addTearDown(room.dispose);
    await room.enter(asHost: true);
    expect(room.state.isHost, true);
    expect(repository.joins, 0);
  });
  test(
    'host URL cannot turn a viewer without publishing credentials into a host',
    () async {
      final repository = _Repository(publisherToken: null);
      final room = LiveRoomNotifier(repository, 'room', 'viewer', null);
      addTearDown(room.dispose);
      await room.enter(asHost: true);
      expect(room.state.isHost, false);
      expect(repository.joins, 1);
    },
  );
}
