import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:livecommerce_mobile/core/config/dev_config.dart';
import 'package:livecommerce_mobile/core/constants/storage_keys.dart';
import 'package:livecommerce_mobile/core/errors/error_handler.dart';
import 'package:livecommerce_mobile/core/network/api_client.dart';
import 'package:livecommerce_mobile/core/network/auth_interceptor.dart';
import 'package:livecommerce_mobile/core/storage/secure_token_storage.dart';
import 'package:livecommerce_mobile/features/auth/data/auth_repository.dart';
import 'package:livecommerce_mobile/features/auth/domain/entities/auth_user.dart';
import 'package:shared_preferences/shared_preferences.dart';

final secureTokenStorageProvider = Provider<SecureTokenStorage>((ref) {
  return SecureTokenStorage();
});

final sharedPreferencesProvider = Provider<SharedPreferences>((ref) {
  throw UnimplementedError('SharedPreferences must be overridden in bootstrap');
});

final authDioProvider = Provider<Dio>((ref) {
  final dio = createAuthDio();
  dio.interceptors.add(LoggingInterceptor());
  return dio;
});

final authRepositoryProvider = Provider<AuthRepository>((ref) {
  final storage = ref.watch(secureTokenStorageProvider);
  final dio = ref.watch(authDioProvider);
  final repository = AuthRepository(
    remote: AuthRemoteDataSource(dio),
    storage: storage,
  );

  dio.interceptors.add(
    AuthInterceptor(
      tokenStorage: storage,
      refreshDio: dio,
      onRefresh: repository.refreshAccessToken,
    ),
  );

  return repository;
});

class AuthState {
  const AuthState({
    this.user,
    this.isLoading = false,
    this.error,
    this.pendingPhone,
    this.isDevGuest = false,
  });

  final AuthUser? user;
  final bool isLoading;
  final String? error;
  final String? pendingPhone;
  final bool isDevGuest;

  bool get isAuthenticated => user != null;

  AuthState copyWith({
    AuthUser? user,
    bool? isLoading,
    String? error,
    String? pendingPhone,
    bool? isDevGuest,
    bool clearUser = false,
    bool clearError = false,
    bool clearPendingPhone = false,
  }) {
    return AuthState(
      user: clearUser ? null : (user ?? this.user),
      isLoading: isLoading ?? this.isLoading,
      error: clearError ? null : (error ?? this.error),
      pendingPhone: clearPendingPhone ? null : (pendingPhone ?? this.pendingPhone),
      isDevGuest: isDevGuest ?? this.isDevGuest,
    );
  }
}

class AuthNotifier extends StateNotifier<AuthState> {
  AuthNotifier(this._repository) : super(const AuthState());

  final AuthRepository _repository;

  Future<void> restoreSession() async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final user = await _repository.restoreSession();
      state = state.copyWith(user: user, isLoading: false, clearUser: user == null);
    } catch (error) {
      state = state.copyWith(
        isLoading: false,
        error: DevConfig.bypassAuth ? null : describeFailure(error),
      );
    }
  }

  void enterDevGuestMode() {
    state = state.copyWith(
      isLoading: false,
      clearError: true,
      isDevGuest: true,
      user: const AuthUser(
        id: '00000000-0000-0000-0000-000000000001',
        username: 'demo',
        displayName: 'Demo User',
      ),
    );
  }

  Future<bool> login(String login, String password) async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final session = await _repository.login(login: login, password: password);
      state = state.copyWith(user: session.user, isLoading: false);
      return true;
    } catch (error) {
      state = state.copyWith(isLoading: false, error: describeFailure(error));
      return false;
    }
  }

  Future<bool> register({
    required String username,
    String? email,
    String? phone,
    required String password,
    required String passwordConfirmation,
  }) async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final session = await _repository.register(
        username: username,
        email: email,
        phone: phone,
        password: password,
        passwordConfirmation: passwordConfirmation,
      );
      state = state.copyWith(
        user: session.user,
        isLoading: false,
        pendingPhone: phone != null && !session.user.phoneVerified ? phone : null,
      );
      return true;
    } catch (error) {
      state = state.copyWith(isLoading: false, error: describeFailure(error));
      return false;
    }
  }

  Future<bool> verifyOtp(String phone, String otp) async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final session = await _repository.verifyOtp(phone: phone, otp: otp);
      state = state.copyWith(
        user: session.user,
        isLoading: false,
        clearPendingPhone: true,
      );
      return true;
    } catch (error) {
      state = state.copyWith(isLoading: false, error: describeFailure(error));
      return false;
    }
  }

  Future<void> resendOtp(String phone) async {
    await _repository.resendOtp(phone);
  }

  Future<bool> updateProfile({
    String? displayName,
    String? bio,
    String? locale,
  }) async {
    if (state.isDevGuest && state.user != null) {
      state = state.copyWith(
        user: state.user!.copyWith(
          displayName: displayName,
          bio: bio,
          locale: locale,
        ),
      );
      return true;
    }

    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final user = await _repository.updateProfile(
        displayName: displayName,
        bio: bio,
        locale: locale,
      );
      state = state.copyWith(user: user, isLoading: false);
      return true;
    } catch (error) {
      state = state.copyWith(isLoading: false, error: describeFailure(error));
      return false;
    }
  }

  Future<void> logout() async {
    await _repository.logout();
    state = const AuthState();
  }
}

final authNotifierProvider =
    StateNotifierProvider<AuthNotifier, AuthState>((ref) {
  return AuthNotifier(ref.watch(authRepositoryProvider));
});

class AppSettings {
  const AppSettings({
    required this.locale,
    required this.themeMode,
  });

  final Locale locale;
  final ThemeMode themeMode;
}

class AppSettingsNotifier extends StateNotifier<AppSettings> {
  AppSettingsNotifier(this._prefs)
      : super(AppSettings(
          locale: _readLocale(_prefs),
          themeMode: _readThemeMode(_prefs),
        ));

  final SharedPreferences _prefs;

  static Locale _readLocale(SharedPreferences prefs) {
    final code = prefs.getString(StorageKeys.locale) ?? 'uz';
    return Locale(code);
  }

  static ThemeMode _readThemeMode(SharedPreferences prefs) {
    final value = prefs.getString(StorageKeys.themeMode) ?? 'system';
    return switch (value) {
      'light' => ThemeMode.light,
      'dark' => ThemeMode.dark,
      _ => ThemeMode.system,
    };
  }

  Future<void> setLocale(Locale locale) async {
    await _prefs.setString(StorageKeys.locale, locale.languageCode);
    state = AppSettings(locale: locale, themeMode: state.themeMode);
  }

  Future<void> setThemeMode(ThemeMode themeMode) async {
    final value = switch (themeMode) {
      ThemeMode.light => 'light',
      ThemeMode.dark => 'dark',
      ThemeMode.system => 'system',
    };
    await _prefs.setString(StorageKeys.themeMode, value);
    state = AppSettings(locale: state.locale, themeMode: themeMode);
  }
}

final appSettingsProvider =
    StateNotifierProvider<AppSettingsNotifier, AppSettings>((ref) {
  return AppSettingsNotifier(ref.watch(sharedPreferencesProvider));
});
