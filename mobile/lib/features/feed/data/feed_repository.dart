import 'package:dio/dio.dart';
import 'package:livecommerce_mobile/features/feed/domain/entities/feed_video.dart';

class FeedRemoteDataSource {
  FeedRemoteDataSource(this._dio);

  final Dio _dio;

  Future<FeedPage> fetchForYou({String? cursor, int limit = 20}) async {
    return _fetchFeed('/feed/for-you', cursor: cursor, limit: limit);
  }

  Future<FeedPage> fetchFollowing({String? cursor, int limit = 20}) async {
    return _fetchFeed('/feed/following', cursor: cursor, limit: limit);
  }

  Future<FeedPage> _fetchFeed(
    String path, {
    String? cursor,
    int limit = 20,
  }) async {
    final response = await _dio.get<Map<String, dynamic>>(
      path,
      queryParameters: {
        if (cursor != null) 'cursor': cursor,
        'limit': limit,
      },
    );

    final body = response.data!;
    final data = body['data'] as List<dynamic>;
    final meta = body['meta'] as Map<String, dynamic>? ?? {};

    return FeedPage(
      videos: data
          .map((item) => _parseFeedItem(item as Map<String, dynamic>))
          .whereType<FeedVideo>()
          .toList(),
      nextCursor: meta['next_cursor'] as String?,
      hasMore: meta['has_more'] as bool? ?? false,
    );
  }

  /// Sprint 6.0 mixed feed wraps videos as `{ type: "video", payload|data: {...} }`.
  /// Live items are skipped until mobile 6.1; legacy flat video payloads still work.
  FeedVideo? _parseFeedItem(Map<String, dynamic> item) {
    final type = item['type'] as String?;
    if (type == 'live') {
      return null;
    }

    final nested = item['payload'] ?? item['data'];
    final Map<String, dynamic> payload;
    if (type == 'video' && nested is Map<String, dynamic>) {
      payload = nested;
    } else if (item['user'] is Map<String, dynamic>) {
      payload = item;
    } else {
      return null;
    }

    return FeedVideo.fromJson(payload);
  }
}

class FeedRepository {
  FeedRepository({required FeedRemoteDataSource remote}) : _remote = remote;

  final FeedRemoteDataSource _remote;

  Future<FeedPage> forYou({String? cursor, int limit = 20}) =>
      _remote.fetchForYou(cursor: cursor, limit: limit);

  Future<FeedPage> following({String? cursor, int limit = 20}) =>
      _remote.fetchFollowing(cursor: cursor, limit: limit);
}
