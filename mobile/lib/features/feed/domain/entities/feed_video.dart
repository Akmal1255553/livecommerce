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
    this.status,
    this.createdAt,
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
  final String? status;
  final String? createdAt;

  factory FeedVideo.fromJson(Map<String, dynamic> json) {
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
      status: json['status'] as String?,
      createdAt: json['created_at'] as String?,
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
