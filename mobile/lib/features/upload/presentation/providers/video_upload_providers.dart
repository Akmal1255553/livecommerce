import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:livecommerce_mobile/core/errors/error_handler.dart';
import 'package:livecommerce_mobile/features/auth/presentation/providers/auth_providers.dart';
import 'package:livecommerce_mobile/features/upload/data/video_upload_repository.dart';
import 'package:livecommerce_mobile/features/upload/domain/entities/video_upload_status.dart';
import 'package:livecommerce_mobile/features/upload/domain/entities/video_upload_session.dart';

final videoUploadRepositoryProvider = Provider<VideoUploadRepository>((ref) {
  final dio = ref.watch(authDioProvider);
  return VideoUploadRepository(
    remote: VideoUploadRemoteDataSource(dio),
  );
});

enum VideoUploadStage {
  /// Nothing picked yet — show the picker.
  idle,

  /// A local file is picked; the form (preview + metadata) is shown.
  picked,

  /// Session created, file streaming to storage.
  uploading,

  /// File uploaded; confirming with the backend (pipeline enqueued).
  confirming,

  /// Backend processing; polling for `published` / `failed`.
  processing,

  /// Published — done.
  published,

  /// Terminal failure (upload or processing error).
  failed,
}

class VideoUploadState {
  const VideoUploadState({
    this.stage = VideoUploadStage.idle,
    this.filePath,
    this.mimeType = '',
    this.fileSize = 0,
    this.progress = 0,
    this.status,
    this.error,
  });

  final VideoUploadStage stage;
  final String? filePath;
  final String mimeType;
  final int fileSize;
  final double progress; // 0..1 while uploading
  final VideoUploadStatus? status;
  final String? error;

  bool get isBusy =>
      stage == VideoUploadStage.uploading ||
      stage == VideoUploadStage.confirming ||
      stage == VideoUploadStage.processing;

  VideoUploadState copyWith({
    VideoUploadStage? stage,
    String? filePath,
    String? mimeType,
    int? fileSize,
    double? progress,
    VideoUploadStatus? status,
    String? error,
    bool clearError = false,
  }) {
    return VideoUploadState(
      stage: stage ?? this.stage,
      filePath: filePath ?? this.filePath,
      mimeType: mimeType ?? this.mimeType,
      fileSize: fileSize ?? this.fileSize,
      progress: progress ?? this.progress,
      status: status ?? this.status,
      error: clearError ? null : (error ?? this.error),
    );
  }
}

class VideoUploadNotifier extends StateNotifier<VideoUploadState> {
  VideoUploadNotifier(this._repository) : super(const VideoUploadState());

  final VideoUploadRepository _repository;
  Timer? _pollTimer;
  bool _polling = false;
  bool _pollInFlight = false;
  int _generation = 0;
  int _pollAttempts = 0;
  VideoUploadSession? _session;
  bool _fileUploaded = false;

  /// Accepts a picked local video. [mimeType] must be one of
  /// video/mp4, video/quicktime, video/webm.
  void selectVideo({required String path, required String mimeType, required int fileSize}) {
    if (state.isBusy) return;
    reset();
    if (mimeType != 'video/mp4' &&
        mimeType != 'video/quicktime' &&
        mimeType != 'video/webm') {
      state = state.copyWith(
        stage: VideoUploadStage.failed,
        error: 'Unsupported video format',
      );
      return;
    }
    state = state.copyWith(
      stage: VideoUploadStage.picked,
      filePath: path,
      mimeType: mimeType,
      fileSize: fileSize,
      progress: 0,
      clearError: true,
    );
  }

  Future<void> upload({
    String? title,
    String? description,
    String visibility = 'public',
  }) async {
    if (state.isBusy) return;
    final generation = ++_generation;
    final path = state.filePath;
    if (path == null || state.fileSize <= 0) {
      state = state.copyWith(stage: VideoUploadStage.failed, error: 'No video selected');
      return;
    }

    if (state.fileSize > VideoUploadRemoteDataSource.maxVideoBytes) {
      state = state.copyWith(
        stage: VideoUploadStage.failed,
        error: 'Video is too large (max 100 MB)',
      );
      return;
    }

    state = state.copyWith(
      stage: VideoUploadStage.uploading,
      progress: 0,
      clearError: true,
    );

    try {
      final session = _session ?? await _repository.initiateUpload(
        mimeType: state.mimeType,
        fileSize: state.fileSize,
        title: title?.trim().isEmpty == true ? null : title?.trim(),
        description: description?.trim().isEmpty == true ? null : description?.trim(),
        visibility: visibility,
      );
      if (!mounted || generation != _generation) return;
      _session = session;

      if (!_fileUploaded) {
        await _repository.putVideoFile(
        session: session,
        filePath: path,
        fileSize: state.fileSize,
        onProgress: (sent, total) {
          if (mounted && generation == _generation && total > 0) {
            state = state.copyWith(progress: (sent / total).clamp(0.0, 1.0));
          }
        },
        );
      }
      if (!mounted || generation != _generation) return;
      _fileUploaded = true;

      state = state.copyWith(stage: VideoUploadStage.confirming, progress: 1);
      final confirmed = await _repository.confirmUpload(session.videoId);
      if (!mounted || generation != _generation) return;
      _applyStatus(confirmed);

      if (state.stage == VideoUploadStage.processing) {
        _startPolling(session.videoId);
      }
    } catch (error) {
      if (!mounted || generation != _generation) return;
      state = state.copyWith(
        stage: VideoUploadStage.failed,
        error: describeFailure(error),
      );
    }
  }

  void _startPolling(String videoId) {
    _pollTimer?.cancel();
    _polling = true;
    _pollAttempts = 0;
    _pollTimer = Timer.periodic(const Duration(seconds: 4), (_) {
      if (!_polling || _pollInFlight) return;
      if (++_pollAttempts > 150) {
        _stopPolling();
        state = state.copyWith(error:
            'Обработка занимает больше времени. Видео сохранено. Нажмите «Проверить статус» позже.');
        return;
      }
      checkStatus();
    });
  }

  void _applyStatus(VideoUploadStatus status) {
    final failed = status.phase == VideoUploadPhase.failed;
    final published = status.phase == VideoUploadPhase.published;
    if (failed || published) _stopPolling();
    state = state.copyWith(
      status: status, progress: 1,
      stage: published ? VideoUploadStage.published
          : failed ? VideoUploadStage.failed : VideoUploadStage.processing,
      clearError: !failed,
      error: failed ? 'Не удалось обработать видео: ${status.failureCode ?? "processing_failed"}' : null,
    );
  }

  void _stopPolling() {
    _polling = false;
    _pollTimer?.cancel();
    _pollTimer = null;
  }

  /// Manual refresh while stuck in `processing`.
  Future<void> checkStatus() async {
    final videoId = state.status?.videoId ?? '';
    if (!state.isBusy || videoId.isEmpty || _pollInFlight) {
      return;
    }
    final generation = _generation;
    _pollInFlight = true;
    try {
      final status = await _repository.fetchStatus(videoId);
      if (mounted && generation == _generation) _applyStatus(status);
    } catch (error) {
      if (mounted && generation == _generation) {
        state = state.copyWith(error: describeFailure(error));
      }
    } finally {
      _pollInFlight = false;
    }
  }

  void reset() {
    ++_generation;
    _session = null;
    _fileUploaded = false;
    _stopPolling();
    state = const VideoUploadState();
  }

  @override
  void dispose() {
    ++_generation;
    _stopPolling();
    super.dispose();
  }
}

final videoUploadNotifierProvider =
    StateNotifierProvider<VideoUploadNotifier, VideoUploadState>((ref) {
  return VideoUploadNotifier(ref.watch(videoUploadRepositoryProvider));
});
