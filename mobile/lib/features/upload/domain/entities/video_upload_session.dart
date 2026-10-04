/// Presigned upload session returned by `POST /videos`.
///
/// The backend returns `{ video, upload_url, upload_method, upload_headers,
/// expires_at }` inside the standard `data` envelope. The caller streams the
/// raw file bytes to [uploadUrl] using [uploadMethod] + [uploadHeaders],
/// then calls `POST /videos/{id}/confirm-upload`.
class VideoUploadSession {
  const VideoUploadSession({
    required this.videoId,
    required this.uploadUrl,
    required this.uploadMethod,
    required this.uploadHeaders,
    this.expiresAt,
  });

  final String videoId;
  final String uploadUrl;
  final String uploadMethod;
  final Map<String, String> uploadHeaders;
  final String? expiresAt;

  factory VideoUploadSession.fromJson(Map<String, dynamic> json) {
    final video = json['video'] as Map<String, dynamic>? ?? const {};
    final headers = json['upload_headers'] as Map<String, dynamic>? ?? const {};

    return VideoUploadSession(
      videoId: (video['id'] ?? json['video_id']).toString(),
      uploadUrl: json['upload_url'] as String? ?? '',
      uploadMethod: (json['upload_method'] as String? ?? 'PUT').toUpperCase(),
      uploadHeaders: headers.map(
        (key, value) => MapEntry(key.toString(), value.toString()),
      ),
      expiresAt: json['expires_at'] as String?,
    );
  }
}
