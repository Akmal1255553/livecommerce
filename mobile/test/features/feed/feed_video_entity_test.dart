import 'package:flutter_test/flutter_test.dart';
import 'package:livecommerce_mobile/features/feed/domain/entities/feed_video.dart';

void main() {
  group('FeedVideo', () {
    test('parses video_url and thumbnail_url for HLS playback', () {
      final video = FeedVideo.fromJson({
        'id': 'vid-1',
        'user': {
          'id': 'u-1',
          'username': 'seller',
          'avatar_url': null,
          'is_verified': true,
        },
        'title': 'Demo',
        'description': null,
        'video_url': 'https://cdn.example.com/videos/vid-1/playlist.m3u8',
        'thumbnail_url': 'https://cdn.example.com/videos/vid-1/thumb.jpg',
        'duration': 42,
        'view_count': 10,
        'like_count': 2,
        'comment_count': 1,
        'is_liked': false,
        'is_bookmarked': false,
        'products': [],
      });

      expect(video.videoUrl, 'https://cdn.example.com/videos/vid-1/playlist.m3u8');
      expect(video.thumbnailUrl, 'https://cdn.example.com/videos/vid-1/thumb.jpg');
      expect(video.duration, 42);
    });

    test('allows missing video_url for thumbnail fallback', () {
      final video = FeedVideo.fromJson({
        'id': 'vid-2',
        'user': {'id': 'u-1', 'username': 'seller'},
        'thumbnail_url': 'https://cdn.example.com/thumb.jpg',
      });

      expect(video.videoUrl, isNull);
      expect(video.thumbnailUrl, isNotNull);
    });
  });
}
