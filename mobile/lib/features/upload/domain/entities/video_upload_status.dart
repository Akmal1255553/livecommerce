/// Lifecycle of a video upload, mirroring the backend `VideoStatus` enum.
enum VideoUploadPhase {
  uploading,
  queued,
  processing,
  published,
  failed,
  unknown;

  static VideoUploadPhase fromValue(String? value) {
    return switch (value) {
      'uploading' => VideoUploadPhase.uploading,
      'queued' => VideoUploadPhase.queued,
      'processing' => VideoUploadPhase.processing,
      'published' => VideoUploadPhase.published,
      'failed' || 'rejected' => VideoUploadPhase.failed,
      _ => VideoUploadPhase.unknown,
    };
  }

  bool get isTerminal => this == VideoUploadPhase.published || this == VideoUploadPhase.failed;
}

/// Snapshot of one video from the `VideoResource` payload.
class VideoUploadStatus {
  const VideoUploadStatus({
    required this.videoId,
    required this.phase,
    this.thumbnailUrl,
    this.videoUrl,
    this.failureCode,
    this.title,
    this.completedSteps = 0,
    this.totalSteps = 0,
    this.currentStep,
  });

  final String videoId;
  final VideoUploadPhase phase;
  final String? thumbnailUrl;
  final String? videoUrl;
  final String? failureCode;
  final String? title;
  final int completedSteps;
  final int totalSteps;
  final String? currentStep;

  /// Accepts a `VideoResource` JSON map; also tolerates a wrapped
  /// `{ "video": { ... } }` payload (confirm-upload response shape).
  factory VideoUploadStatus.fromJson(Map<String, dynamic> json) {
    final video = json['video'] is Map<String, dynamic>
        ? json['video'] as Map<String, dynamic>
        : json;

    final steps = (video['processing_steps'] as List<dynamic>? ?? [])
        .whereType<Map<String, dynamic>>().toList();
    final active = steps.where((s) => s['status'] == 'running' || s['status'] == 'processing').firstOrNull;
    return VideoUploadStatus(
      videoId: (video['id'] ?? json['video_id']).toString(),
      phase: VideoUploadPhase.fromValue(video['status'] as String?),
      thumbnailUrl: video['thumbnail_url'] as String?,
      videoUrl: video['video_url'] as String?,
      failureCode: video['failure_code'] as String?,
      title: video['title'] as String?,
      completedSteps: steps.where((s) => s['status'] == 'completed').length,
      totalSteps: steps.length,
      currentStep: active?['step'] as String?,
    );
  }
}
