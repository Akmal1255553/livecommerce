import 'dart:async';
import 'package:dio/dio.dart';

import 'package:flutter_test/flutter_test.dart';
import 'package:livecommerce_mobile/features/upload/data/video_upload_repository.dart';
import 'package:livecommerce_mobile/features/upload/domain/entities/video_upload_session.dart';
import 'package:livecommerce_mobile/features/upload/domain/entities/video_upload_status.dart';
import 'package:livecommerce_mobile/features/upload/presentation/providers/video_upload_providers.dart';

class UploadRepositoryFake implements VideoUploadRepository {
  int sessions = 0;
  int puts = 0;
  int confirms = 0;
  bool loseFirstConfirmation = false;
  Completer<void>? pendingPut;
  VideoUploadPhase phase = VideoUploadPhase.published;
  Object? statusError;
  String? failureCode;

  @override
  Future<VideoUploadSession> initiateUpload({required String mimeType,
    required int fileSize, String? title, String? description,
    String visibility = 'public'}) async {
    sessions++;
    return const VideoUploadSession(videoId: 'v1',
      uploadUrl: 'https://storage.example/video', uploadMethod: 'PUT',
      uploadHeaders: {});
  }

  @override
  Future<void> putVideoFile({required VideoUploadSession session,
    required String filePath, required int fileSize,
    void Function(int, int)? onProgress}) async {
    puts++;
    if (pendingPut != null) await pendingPut!.future;
    onProgress?.call(fileSize, fileSize);
  }

  @override
  Future<VideoUploadStatus> confirmUpload(String videoId) async {
    confirms++;
    if (loseFirstConfirmation && confirms == 1) throw Exception('connection lost');
    return VideoUploadStatus(videoId: videoId, phase: phase);
  }

  @override
  Future<VideoUploadStatus> fetchStatus(String videoId) async {
    if (statusError != null) throw statusError!;
    return VideoUploadStatus(videoId: videoId, phase: phase, failureCode: failureCode);
  }
}

void main() {
  late UploadRepositoryFake repository;
  late VideoUploadNotifier notifier;
  setUp(() {
    repository = UploadRepositoryFake();
    notifier = VideoUploadNotifier(repository);
    notifier.selectVideo(path: 'blob:test', mimeType: 'video/mp4', fileSize: 123);
  });
  tearDown(() { if (notifier.mounted) notifier.dispose(); });

  test('immediately published response does not wait for polling', () async {
    await notifier.upload();
    expect(notifier.state.stage, VideoUploadStage.published);
    expect(notifier.state.progress, 1);
  });

  test('lost confirmation retries without a duplicate session or PUT', () async {
    repository.loseFirstConfirmation = true;
    await notifier.upload();
    expect(notifier.state.stage, VideoUploadStage.failed);
    await notifier.upload();
    expect(notifier.state.stage, VideoUploadStage.published);
    expect(repository.sessions, 1);
    expect(repository.puts, 1);
    expect(repository.confirms, 2);
  });

  test('double tap does not start a second upload', () async {
    repository.pendingPut = Completer<void>();
    final first = notifier.upload();
    await Future<void>.delayed(Duration.zero);
    await notifier.upload();
    repository.pendingPut!.complete();
    await first;
    expect(repository.sessions, 1);
    expect(repository.puts, 1);
  });

  test('disposing during PUT ignores late callbacks and confirmation', () async {
    repository.pendingPut = Completer<void>();
    final upload = notifier.upload();
    await Future<void>.delayed(Duration.zero);
    notifier.dispose();
    repository.pendingPut!.complete();
    await upload;
    expect(repository.confirms, 0);
  });

  test('server rejection is terminal and has a visible error', () async {
    repository.phase = VideoUploadPhase.fromValue('rejected');
    await notifier.upload();
    expect(notifier.state.stage, VideoUploadStage.failed);
    expect(notifier.state.error, isNotEmpty);
  });

  test('oversized video never creates an upload session', () async {
    notifier.selectVideo(path: 'blob:large', mimeType: 'video/mp4',
        fileSize: VideoUploadRemoteDataSource.maxVideoBytes + 1);
    await notifier.upload();
    expect(repository.sessions, 0);
    expect(notifier.state.stage, VideoUploadStage.failed);
  });

  test('not found stops the processing spinner and allows a fresh upload', () async {
    repository.phase = VideoUploadPhase.processing;
    await notifier.upload();
    final request = RequestOptions(path: '/videos/v1/upload-status');
    repository.statusError = DioException(requestOptions: request,
      response: Response(requestOptions: request, statusCode: 404));
    await notifier.checkStatus();
    expect(notifier.state.stage, VideoUploadStage.failed);
    expect(notifier.state.isBusy, false);
    repository.statusError = null;
    repository.phase = VideoUploadPhase.published;
    await notifier.upload();
    expect(repository.sessions, 2);
    expect(notifier.state.stage, VideoUploadStage.published);
  });

  test('duration rejection explains the limit instead of processing forever', () async {
    repository.phase = VideoUploadPhase.processing;
    await notifier.upload();
    repository.phase = VideoUploadPhase.failed;
    repository.failureCode = 'duration_exceeded';
    await notifier.checkStatus();
    expect(notifier.state.stage, VideoUploadStage.failed);
    expect(notifier.state.error, contains('60 секунд'));
    expect(notifier.state.isBusy, false);
  });
}
