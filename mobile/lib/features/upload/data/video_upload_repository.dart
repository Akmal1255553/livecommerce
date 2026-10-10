import 'package:dio/dio.dart';
import 'package:livecommerce_mobile/features/upload/data/presigned_put_uploader.dart';
import 'package:livecommerce_mobile/features/upload/domain/entities/video_upload_session.dart';
import 'package:livecommerce_mobile/features/upload/domain/entities/video_upload_status.dart';

/// Calls the backend upload APIs (`POST /videos`, confirm, status) and, via
/// [PresignedPutUploader], streams the file to the returned presigned URL.
class VideoUploadRemoteDataSource {
  VideoUploadRemoteDataSource(this._dio, [this._putUploader = const PresignedPutUploader()]);

  final Dio _dio;
  final PresignedPutUploader _putUploader;

  static const int maxVideoBytes = 52428800; // 50 MB — Supabase Free limit.

  Future<VideoUploadSession> initiateUpload({
    required String mimeType,
    required int fileSize,
    String? title,
    String? description,
    String visibility = 'public',
  }) async {
    final response = await _dio.post<Map<String, dynamic>>(
      '/videos',
      data: {
        'title': title,
        'description': description,
        'visibility': visibility,
        'mime_type': mimeType,
        'file_size': fileSize,
      },
    );
    final data = response.data?['data'] as Map<String, dynamic>? ?? const {};
    return VideoUploadSession.fromJson(data);
  }

  Future<void> putVideoFile({
    required VideoUploadSession session,
    required String filePath,
    required int fileSize,
    void Function(int sent, int total)? onProgress,
  }) async {
    await _putUploader.upload(
      url: session.uploadUrl,
      filePath: filePath,
      fileSize: fileSize,
      headers: session.uploadHeaders,
      onProgress: onProgress,
    );
  }

  Future<VideoUploadStatus> confirmUpload(String videoId) async {
    final response = await _dio.post<Map<String, dynamic>>(
      '/videos/$videoId/confirm-upload',
    );
    final data = response.data?['data'] as Map<String, dynamic>? ?? const {};
    return VideoUploadStatus.fromJson(data);
  }

  Future<void> attachProducts(String videoId, List<String> productIds) async {
    await _dio.put<dynamic>('/videos/$videoId/products', data: {
      'products': [for (var i = 0; i < productIds.length; i++) {
        'product_id': productIds[i], 'sort_order': i, 'is_featured': i == 0,
      }],
    });
  }

  Future<VideoUploadStatus> fetchStatus(String videoId) async {
    final response = await _dio.get<Map<String, dynamic>>('/videos/$videoId/upload-status');
    final data = response.data?['data'] as Map<String, dynamic>? ?? const {};
    return VideoUploadStatus.fromJson(data);
  }
}

class VideoUploadRepository {
  VideoUploadRepository({required VideoUploadRemoteDataSource remote})
      : _remote = remote;

  final VideoUploadRemoteDataSource _remote;

  Future<VideoUploadSession> initiateUpload({
    required String mimeType,
    required int fileSize,
    String? title,
    String? description,
    String visibility = 'public',
  }) =>
      _remote.initiateUpload(
        mimeType: mimeType,
        fileSize: fileSize,
        title: title,
        description: description,
        visibility: visibility,
      );

  Future<void> putVideoFile({
    required VideoUploadSession session,
    required String filePath,
    required int fileSize,
    void Function(int sent, int total)? onProgress,
  }) =>
      _remote.putVideoFile(
        session: session,
        filePath: filePath,
        fileSize: fileSize,
        onProgress: onProgress,
      );

  Future<VideoUploadStatus> confirmUpload(String videoId) =>
      _remote.confirmUpload(videoId);

  Future<void> attachProducts(String videoId, List<String> productIds) =>
      _remote.attachProducts(videoId, productIds);

  Future<VideoUploadStatus> fetchStatus(String videoId) =>
      _remote.fetchStatus(videoId);
}
