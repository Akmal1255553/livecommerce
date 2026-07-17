import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:livecommerce_mobile/core/errors/error_handler.dart';
import 'package:livecommerce_mobile/features/auth/presentation/providers/auth_providers.dart';
import 'package:livecommerce_mobile/features/feed/data/feed_repository.dart';
import 'package:livecommerce_mobile/features/feed/domain/entities/feed_video.dart';

final feedRepositoryProvider = Provider<FeedRepository>((ref) {
  final dio = ref.watch(authDioProvider);
  return FeedRepository(remote: FeedRemoteDataSource(dio));
});

enum FeedTab { forYou, following }

class FeedState {
  const FeedState({
    this.videos = const [],
    this.isLoading = false,
    this.isLoadingMore = false,
    this.error,
    this.nextCursor,
    this.hasMore = false,
    this.tab = FeedTab.forYou,
  });

  final List<FeedVideo> videos;
  final bool isLoading;
  final bool isLoadingMore;
  final String? error;
  final String? nextCursor;
  final bool hasMore;
  final FeedTab tab;

  FeedState copyWith({
    List<FeedVideo>? videos,
    bool? isLoading,
    bool? isLoadingMore,
    String? error,
    String? nextCursor,
    bool? hasMore,
    FeedTab? tab,
    bool clearError = false,
    bool clearVideos = false,
  }) {
    return FeedState(
      videos: clearVideos ? const [] : (videos ?? this.videos),
      isLoading: isLoading ?? this.isLoading,
      isLoadingMore: isLoadingMore ?? this.isLoadingMore,
      error: clearError ? null : (error ?? this.error),
      nextCursor: nextCursor ?? this.nextCursor,
      hasMore: hasMore ?? this.hasMore,
      tab: tab ?? this.tab,
    );
  }
}

class FeedNotifier extends StateNotifier<FeedState> {
  FeedNotifier(this._repository) : super(const FeedState());

  final FeedRepository _repository;
  final Set<String> _viewedIds = {};

  Future<void> load({FeedTab? tab}) async {
    final selectedTab = tab ?? state.tab;
    state = state.copyWith(
      tab: selectedTab,
      isLoading: true,
      clearError: true,
      clearVideos: true,
      nextCursor: null,
      hasMore: false,
    );

    try {
      final page = selectedTab == FeedTab.forYou
          ? await _repository.forYou()
          : await _repository.following();

      state = state.copyWith(
        videos: page.videos,
        nextCursor: page.nextCursor,
        hasMore: page.hasMore,
        isLoading: false,
      );

      if (page.videos.isNotEmpty) {
        // Fire-and-forget view for the first card in the vertical feed.
        unawaited(recordView(page.videos.first.id));
      }
    } on DioException catch (error) {
      state = state.copyWith(
        isLoading: false,
        error: error.response?.data?['message']?.toString() ?? error.message,
      );
    } catch (error) {
      state = state.copyWith(isLoading: false, error: error.toString());
    }
  }

  Future<void> loadMore() async {
    if (state.isLoadingMore || !state.hasMore || state.nextCursor == null) {
      return;
    }

    state = state.copyWith(isLoadingMore: true, clearError: true);

    try {
      final page = state.tab == FeedTab.forYou
          ? await _repository.forYou(cursor: state.nextCursor)
          : await _repository.following(cursor: state.nextCursor);

      state = state.copyWith(
        videos: [...state.videos, ...page.videos],
        nextCursor: page.nextCursor,
        hasMore: page.hasMore,
        isLoadingMore: false,
      );
    } on DioException catch (error) {
      state = state.copyWith(
        isLoadingMore: false,
        error: error.response?.data?['message']?.toString() ?? error.message,
      );
    } catch (error) {
      state = state.copyWith(isLoadingMore: false, error: error.toString());
    }
  }

  Future<void> switchTab(FeedTab tab) async {
    if (tab == state.tab && state.videos.isNotEmpty) {
      return;
    }
    await load(tab: tab);
  }

  Future<String?> toggleLike(String videoId) async {
    final index = state.videos.indexWhere((v) => v.id == videoId);
    if (index < 0) {
      return 'Video not found';
    }

    final video = state.videos[index];
    final nextLiked = !video.isLiked;
    final optimistic = video.copyWith(
      isLiked: nextLiked,
      likeCount: (video.likeCount + (nextLiked ? 1 : -1)).clamp(0, 1 << 30),
    );
    _replaceVideo(index, optimistic);

    try {
      if (nextLiked) {
        await _repository.likeVideo(videoId);
      } else {
        await _repository.unlikeVideo(videoId);
      }
      return null;
    } catch (error) {
      _replaceVideo(index, video);
      return describeFailure(error);
    }
  }

  Future<String?> toggleBookmark(String videoId) async {
    final index = state.videos.indexWhere((v) => v.id == videoId);
    if (index < 0) {
      return 'Video not found';
    }

    final video = state.videos[index];
    final next = !video.isBookmarked;
    _replaceVideo(index, video.copyWith(isBookmarked: next));

    try {
      if (next) {
        await _repository.bookmarkVideo(videoId);
      } else {
        await _repository.unbookmarkVideo(videoId);
      }
      return null;
    } catch (error) {
      _replaceVideo(index, video);
      return describeFailure(error);
    }
  }

  Future<void> recordView(String videoId) async {
    if (_viewedIds.contains(videoId)) {
      return;
    }
    _viewedIds.add(videoId);

    final index = state.videos.indexWhere((v) => v.id == videoId);
    if (index >= 0) {
      final video = state.videos[index];
      _replaceVideo(index, video.copyWith(viewCount: video.viewCount + 1));
    }

    try {
      await _repository.recordView(videoId);
    } catch (_) {
      // Views are best-effort for UX; ignore network failures.
    }
  }

  void bumpCommentCount(String videoId, {int by = 1}) {
    final index = state.videos.indexWhere((v) => v.id == videoId);
    if (index < 0) {
      return;
    }
    final video = state.videos[index];
    _replaceVideo(
      index,
      video.copyWith(commentCount: (video.commentCount + by).clamp(0, 1 << 30)),
    );
  }

  void _replaceVideo(int index, FeedVideo video) {
    final videos = [...state.videos];
    videos[index] = video;
    state = state.copyWith(videos: videos);
  }
}

final feedNotifierProvider =
    StateNotifierProvider<FeedNotifier, FeedState>((ref) {
  return FeedNotifier(ref.watch(feedRepositoryProvider));
});
