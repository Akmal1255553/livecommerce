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
          .map((item) => FeedVideo.fromJson(item as Map<String, dynamic>))
          .toList(),
      nextCursor: meta['next_cursor'] as String?,
      hasMore: meta['has_more'] as bool? ?? false,
    );
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
