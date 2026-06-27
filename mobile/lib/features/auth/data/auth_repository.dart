import 'package:dio/dio.dart';
import 'package:livecommerce_mobile/core/constants/api_constants.dart';
import 'package:livecommerce_mobile/core/storage/secure_token_storage.dart';
import 'package:livecommerce_mobile/features/auth/domain/entities/auth_session.dart';
import 'package:livecommerce_mobile/features/auth/domain/entities/auth_user.dart';

class AuthRemoteDataSource {
  AuthRemoteDataSource(this._dio);

  final Dio _dio;

  Future<AuthSession> register({
    required String username,
    String? email,
    String? phone,
    required String password,
    required String passwordConfirmation,
  }) async {
    final response = await _dio.post<Map<String, dynamic>>(
      '/auth/register',
      data: {
        'username': username,
        if (email != null) 'email': email,
        if (phone != null) 'phone': phone,
        'password': password,
        'password_confirmation': passwordConfirmation,
      },
    );

    return _parseAuthResponse(response.data!);
  }

  Future<AuthSession> login({
    required String login,
    required String password,
  }) async {
    final response = await _dio.post<Map<String, dynamic>>(
      '/auth/login',
      data: {
        'login': login,
        'password': password,
      },
    );

    return _parseAuthResponse(response.data!);
  }

  Future<AuthSession> verifyOtp({
    required String phone,
    required String otp,
  }) async {
    final response = await _dio.post<Map<String, dynamic>>(
      '/auth/verify-otp',
      data: {
        'phone': phone,
        'otp': otp,
      },
    );

    return _parseAuthResponse(response.data!);
  }

  Future<void> resendOtp(String phone) async {
    await _dio.post<Map<String, dynamic>>(
      '/auth/resend-otp',
      data: {'phone': phone},
    );
  }

  Future<Map<String, String>> refresh(String refreshToken) async {
    final response = await _dio.post<Map<String, dynamic>>(
      '/auth/refresh',
      data: {'refresh_token': refreshToken},
    );

    final data = response.data!['data'] as Map<String, dynamic>;
    return {
      'access_token': data['access_token'] as String,
      'refresh_token': data['refresh_token'] as String,
    };
  }

  Future<void> logout(String refreshToken, String accessToken) async {
    await _dio.post<Map<String, dynamic>>(
      '/auth/logout',
      data: {'refresh_token': refreshToken},
      options: Options(headers: {'Authorization': 'Bearer $accessToken'}),
    );
  }

  Future<AuthUser> fetchProfile(String accessToken) async {
    final response = await _dio.get<Map<String, dynamic>>(
      '/me',
      options: Options(headers: {'Authorization': 'Bearer $accessToken'}),
    );

    final data = response.data!['data'] as Map<String, dynamic>;
    return AuthUser.fromJson(data);
  }

  Future<AuthUser> updateProfile({
    required String accessToken,
    String? displayName,
    String? bio,
    String? locale,
  }) async {
    final response = await _dio.put<Map<String, dynamic>>(
      '/me',
      data: {
        if (displayName != null) 'display_name': displayName,
        if (bio != null) 'bio': bio,
        if (locale != null) 'locale': locale,
      },
      options: Options(headers: {'Authorization': 'Bearer $accessToken'}),
    );

    final data = response.data!['data'] as Map<String, dynamic>;
    return AuthUser.fromJson(data);
  }

  AuthSession _parseAuthResponse(Map<String, dynamic> body) {
    final data = body['data'] as Map<String, dynamic>;
    return AuthSession(
      user: AuthUser.fromJson(data['user'] as Map<String, dynamic>),
      accessToken: data['access_token'] as String,
      refreshToken: data['refresh_token'] as String,
    );
  }
}

class AuthRepository {
  AuthRepository({
    required AuthRemoteDataSource remote,
    required SecureTokenStorage storage,
  })  : _remote = remote,
        _storage = storage;

  final AuthRemoteDataSource _remote;
  final SecureTokenStorage _storage;

  Future<AuthSession> register({
    required String username,
    String? email,
    String? phone,
    required String password,
    required String passwordConfirmation,
  }) async {
    final session = await _remote.register(
      username: username,
      email: email,
      phone: phone,
      password: password,
      passwordConfirmation: passwordConfirmation,
    );
    await _storage.saveTokens(
      accessToken: session.accessToken,
      refreshToken: session.refreshToken,
    );
    return session;
  }

  Future<AuthSession> login({
    required String login,
    required String password,
  }) async {
    final session = await _remote.login(login: login, password: password);
    await _storage.saveTokens(
      accessToken: session.accessToken,
      refreshToken: session.refreshToken,
    );
    return session;
  }

  Future<AuthSession> verifyOtp({
    required String phone,
    required String otp,
  }) async {
    final session = await _remote.verifyOtp(phone: phone, otp: otp);
    await _storage.saveTokens(
      accessToken: session.accessToken,
      refreshToken: session.refreshToken,
    );
    return session;
  }

  Future<void> resendOtp(String phone) => _remote.resendOtp(phone);

  Future<String?> refreshAccessToken(String refreshToken) async {
    final tokens = await _remote.refresh(refreshToken);
    await _storage.saveTokens(
      accessToken: tokens['access_token']!,
      refreshToken: tokens['refresh_token']!,
    );
    return tokens['access_token'];
  }

  Future<void> logout() async {
    final access = await _storage.readAccessToken();
    final refresh = await _storage.readRefreshToken();
    if (access != null && refresh != null) {
      try {
        await _remote.logout(refresh, access);
      } catch (_) {
        // Clear local session even if remote logout fails.
      }
    }
    await _storage.clearTokens();
  }

  Future<AuthUser?> restoreSession() async {
    final access = await _storage.readAccessToken();
    if (access == null) {
      return null;
    }
    try {
      return await _remote.fetchProfile(access);
    } catch (_) {
      final refresh = await _storage.readRefreshToken();
      if (refresh == null) {
        await _storage.clearTokens();
        return null;
      }
      final newAccess = await refreshAccessToken(refresh);
      if (newAccess == null) {
        return null;
      }
      return _remote.fetchProfile(newAccess);
    }
  }

  Future<AuthUser> updateProfile({
    String? displayName,
    String? bio,
    String? locale,
  }) async {
    final access = await _storage.readAccessToken();
    if (access == null) {
      throw StateError('Not authenticated');
    }
    return _remote.updateProfile(
      accessToken: access,
      displayName: displayName,
      bio: bio,
      locale: locale,
    );
  }
}

Dio createAuthDio() {
  return Dio(BaseOptions(
    baseUrl: ApiConstants.baseUrl,
    connectTimeout: ApiConstants.connectTimeout,
    receiveTimeout: ApiConstants.receiveTimeout,
    headers: {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
    },
  ));
}
