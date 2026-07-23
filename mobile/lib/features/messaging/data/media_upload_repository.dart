import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:livecommerce_mobile/features/auth/presentation/providers/auth_providers.dart';

class MediaUploadRemoteDataSource {
  MediaUploadRemoteDataSource(this._dio);

  final Dio _dio;

  /// Presign → PUT bytes → returns durable [publicUrl] for message payloads.
  Future<String> uploadMessageImage({
    required String fileName,
    required String mimeType,
    required Uint8List bytes,
  }) async {
    final response = await _dio.post<Map<String, dynamic>>(
      '/media/presigned-url',
      data: {
        'purpose': 'message_image',
        'file_name': fileName,
        'mime_type': mimeType,
        'file_size': bytes.length,
      },
    );
    final data = response.data!['data'] as Map<String, dynamic>;
    final uploadUrl = data['upload_url'] as String;
    final method = (data['upload_method'] as String? ?? 'PUT').toUpperCase();
    final headers = Map<String, dynamic>.from(
      data['upload_headers'] as Map? ?? {},
    );
    final publicUrl = data['public_url'] as String? ?? uploadUrl;

    final putDio = Dio();
    await putDio.request<void>(
      uploadUrl,
      data: bytes,
      options: Options(
        method: method,
        headers: {
          ...headers,
          Headers.contentLengthHeader: bytes.length,
        },
      ),
    );

    return publicUrl;
  }
}

class MediaUploadRepository {
  MediaUploadRepository({required MediaUploadRemoteDataSource remote})
      : _remote = remote;

  final MediaUploadRemoteDataSource _remote;

  Future<String> uploadMessageImage({
    required String fileName,
    required String mimeType,
    required Uint8List bytes,
  }) =>
      _remote.uploadMessageImage(
        fileName: fileName,
        mimeType: mimeType,
        bytes: bytes,
      );
}

final mediaUploadRepositoryProvider = Provider<MediaUploadRepository>((ref) {
  final dio = ref.watch(authDioProvider);
  return MediaUploadRepository(remote: MediaUploadRemoteDataSource(dio));
});
