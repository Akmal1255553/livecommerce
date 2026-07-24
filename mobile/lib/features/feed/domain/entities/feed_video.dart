import 'product_card.dart';

class FeedVideoUser {
  const FeedVideoUser({
    required this.id,
    required this.username,
    this.avatarUrl,
    this.isVerified = false,
  });

  final String id;
  final String username;
  final String? avatarUrl;
  final bool isVerified;

  factory FeedVideoUser.fromJson(Map<String, dynamic> json) {
    return FeedVideoUser(
      id: json['id'] as String,
      username: json['username'] as String,
      avatarUrl: json['avatar_url'] as String?,
      isVerified: json['is_verified'] as bool? ?? false,
    );
  }
}

class FeedVideo {
  const FeedVideo({
    required this.id,
    required this.user,
    this.title,
    this.description,
    this.videoUrl,
    this.thumbnailUrl,
    this.duration,
    this.viewCount = 0,
    this.likeCount = 0,
    this.commentCount = 0,
    this.isLiked = false,
    this.isBookmarked = false,
    this.status,
    this.createdAt,
    this.products = const [],
  });

  final String id;
  final FeedVideoUser user;
  final String? title;
  final String? description;
  final String? videoUrl;
  final String? thumbnailUrl;
  final int? duration;
  final int viewCount;
  final int likeCount;
  final int commentCount;
  final bool isLiked;
  final bool isBookmarked;
  final String? status;
  final String? createdAt;
  final List<VideoProductTag> products;

  FeedVideo copyWith({
    int? viewCount,
    int? likeCount,
    int? commentCount,
    bool? isLiked,
    bool? isBookmarked,
  }) {
    return FeedVideo(
      id: id,
      user: user,
      title: title,
      description: description,
      videoUrl: videoUrl,
      thumbnailUrl: thumbnailUrl,
      duration: duration,
      viewCount: viewCount ?? this.viewCount,
      likeCount: likeCount ?? this.likeCount,
      commentCount: commentCount ?? this.commentCount,
      isLiked: isLiked ?? this.isLiked,
      isBookmarked: isBookmarked ?? this.isBookmarked,
      status: status,
      createdAt: createdAt,
      products: products,
    );
  }

  factory FeedVideo.fromJson(Map<String, dynamic> json) {
    final productsJson = json['products'] as List<dynamic>? ?? [];

    return FeedVideo(
      id: json['id'] as String,
      user: FeedVideoUser.fromJson(json['user'] as Map<String, dynamic>),
      title: json['title'] as String?,
      description: json['description'] as String?,
      videoUrl: json['video_url'] as String?,
      thumbnailUrl: json['thumbnail_url'] as String?,
      duration: json['duration'] as int?,
      viewCount: json['view_count'] as int? ?? 0,
      likeCount: json['like_count'] as int? ?? 0,
      commentCount: json['comment_count'] as int? ?? 0,
      isLiked: json['is_liked'] as bool? ?? false,
      isBookmarked: json['is_bookmarked'] as bool? ?? false,
      status: json['status'] as String?,
      createdAt: json['created_at'] as String?,
      products: productsJson
          .map((item) => VideoProductTag.fromJson(item as Map<String, dynamic>))
          .toList(),
    );
  }
}

class FeedPage {
  const FeedPage({
    required this.videos,
    this.nextCursor,
    this.hasMore = false,
  });

  final List<FeedVideo> videos;
  final String? nextCursor;
  final bool hasMore;
}

class VideoComment {
  const VideoComment({
    required this.id,
    required this.body,
    required this.username,
    this.avatarUrl,
    this.createdAt,
  });

  final String id;
  final String body;
  final String username;
  final String? avatarUrl;
  final String? createdAt;

  factory VideoComment.fromJson(Map<String, dynamic> json) {
    final user = json['user'] as Map<String, dynamic>?;
    return VideoComment(
      id: json['id'].toString(),
      body: json['body'] as String? ?? '',
      username: user?['username'] as String? ?? 'user',
      avatarUrl: user?['avatar_url'] as String?,
      createdAt: json['created_at'] as String?,
    );
  }
}
