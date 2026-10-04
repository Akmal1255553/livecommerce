import 'dart:io';

/// Streams a local file to a presigned URL (e.g. S3 PUT) without buffering
/// the whole video in memory.
///
/// A plain `dart:io` client is used instead of Dio because Dio sends stream
/// bodies with chunked transfer-encoding, which presigned S3 URLs reject —
/// they require an explicit `Content-Length`. Dart's `HttpClientRequest`
/// implements `IOSink`, so we can count bytes as they are written to report
/// upload progress.
///
/// Native implementation; browsers use the conditional web implementation.
class PresignedPutUploader {
  const PresignedPutUploader();

  Future<void> upload({
    required String url,
    required String filePath,
    required int fileSize,
    Map<String, String> headers = const {},
    void Function(int sent, int total)? onProgress,
  }) async {
    final client = HttpClient();
    HttpClientRequest? request;
    var completed = false;
    try {
      final req = await client
          .openUrl('PUT', Uri.parse(url))
          .timeout(const Duration(seconds: 15));
      request = req;

      req.headers.contentType = ContentType.parse(
        headers['Content-Type'] ?? 'application/octet-stream',
      );
      // Content-Length must be explicit for presigned PUT (no chunked body).
      req.headers.set('Content-Length', fileSize.toString());
      headers.forEach((key, value) {
        if (key.toLowerCase() != 'content-type') {
          req.headers.set(key, value);
        }
      });

      int sent = 0;
      await for (final chunk in File(filePath).openRead()) {
        req.add(chunk);
        sent += chunk.length;
        onProgress?.call(sent, fileSize);
      }

      final response = await req.close();
      completed = true;
      if (response.statusCode < 200 || response.statusCode >= 300) {
        throw HttpException(
          'Upload failed with status ${response.statusCode}',
          uri: Uri.parse(url),
        );
      }
    } finally {
      if (!completed) {
        request?.abort();
      }
      client.close(force: true);
    }
  }
}
