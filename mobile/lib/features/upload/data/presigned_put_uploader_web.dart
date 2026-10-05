import 'package:dio/dio.dart';
import 'package:image_picker/image_picker.dart';

/// Browser files have blob URLs, not operating-system paths. This client is
/// separate from the API client so JWTs never reach object storage.
class PresignedPutUploader {
  const PresignedPutUploader();

  Future<void> upload({
    required String url,
    required String filePath,
    required int fileSize,
    Map<String, String> headers = const {},
    void Function(int sent, int total)? onProgress,
  }) async {
    final bytes = await XFile(filePath).readAsBytes();
    if (bytes.length != fileSize || fileSize > 52428800) {
      throw StateError('Selected video size changed or exceeds 50 MB');
    }
    final client = Dio(BaseOptions(
      connectTimeout: const Duration(seconds: 30),
      sendTimeout: const Duration(minutes: 10),
      receiveTimeout: const Duration(minutes: 1),
    ));
    try {
      await client.put<void>(
        url,
        data: bytes,
        options: Options(headers: {
          for (final entry in headers.entries)
            if (!{'content-length', 'host', 'connection'}
                .contains(entry.key.toLowerCase()))
              entry.key: entry.value,
        }),
        onSendProgress: onProgress,
      );
    } finally {
      client.close(force: true);
    }
  }
}
