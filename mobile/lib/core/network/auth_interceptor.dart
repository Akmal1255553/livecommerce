import 'package:dio/dio.dart';
import 'package:livecommerce_mobile/core/storage/secure_token_storage.dart';

typedef TokenRefreshCallback = Future<String?> Function(String refreshToken);

class AuthInterceptor extends Interceptor {
  AuthInterceptor({
    required SecureTokenStorage tokenStorage,
    required Dio refreshDio,
    required TokenRefreshCallback onRefresh,
  })  : _tokenStorage = tokenStorage,
        _refreshDio = refreshDio,
        _onRefresh = onRefresh;

  final SecureTokenStorage _tokenStorage;
  final Dio _refreshDio;
  final TokenRefreshCallback _onRefresh;
  bool _isRefreshing = false;

  @override
  Future<void> onRequest(
    RequestOptions options,
    RequestInterceptorHandler handler,
  ) async {
    final token = await _tokenStorage.readAccessToken();
    if (token != null && token.isNotEmpty) {
      options.headers['Authorization'] = 'Bearer $token';
    }
    handler.next(options);
  }

  @override
  Future<void> onError(
    DioException err,
    ErrorInterceptorHandler handler,
  ) async {
    if (err.response?.statusCode != 401 || _isRefreshing) {
      handler.next(err);
      return;
    }

    final refreshToken = await _tokenStorage.readRefreshToken();
    if (refreshToken == null || refreshToken.isEmpty) {
      handler.next(err);
      return;
    }

    try {
      _isRefreshing = true;
      final newAccess = await _onRefresh(refreshToken);
      if (newAccess == null) {
        await _tokenStorage.clearTokens();
        handler.next(err);
        return;
      }

      final request = err.requestOptions;
      request.headers['Authorization'] = 'Bearer $newAccess';
      final response = await _refreshDio.fetch(request);
      handler.resolve(response);
    } catch (_) {
      await _tokenStorage.clearTokens();
      handler.next(err);
    } finally {
      _isRefreshing = false;
    }
  }
}
