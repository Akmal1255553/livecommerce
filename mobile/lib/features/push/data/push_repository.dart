import 'package:dio/dio.dart';

/// Registers / unregisters the device FCM token via `POST /devices`.
class PushRepository {
  PushRepository(this._dio);

  final Dio _dio;

  Future<void> registerToken({
    required String token,
    required String platform,
    String? deviceId,
    String? appVersion,
  }) async {
    await _dio.post<Map<String, dynamic>>(
      '/devices',
      data: {
        'fcm_token': token,
        'platform': platform,
        if (deviceId != null) 'device_id': deviceId,
        if (appVersion != null) 'app_version': appVersion,
      },
    );
  }

  Future<void> unregisterToken(String token) async {
    await _dio.delete<Map<String, dynamic>>('/devices/$token');
  }
}
