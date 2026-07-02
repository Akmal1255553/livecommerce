// ignore: unused_import
import 'package:intl/intl.dart' as intl;
import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for Russian (`ru`).
class AppLocalizationsRu extends AppLocalizations {
  AppLocalizationsRu([String locale = 'ru']) : super(locale);

  @override
  String get appTitle => 'LiveCommerce';

  @override
  String get loginTitle => 'Вход';

  @override
  String get registerTitle => 'Регистрация';

  @override
  String get otpTitle => 'Подтверждение телефона';

  @override
  String get profileTitle => 'Профиль';

  @override
  String get settingsTitle => 'Настройки';

  @override
  String get emailLabel => 'Email';

  @override
  String get phoneLabel => 'Телефон';

  @override
  String get usernameLabel => 'Имя пользователя';

  @override
  String get passwordLabel => 'Пароль';

  @override
  String get passwordConfirmLabel => 'Подтверждение пароля';

  @override
  String get loginFieldLabel => 'Email, телефон или username';

  @override
  String get otpLabel => 'SMS-код';

  @override
  String get loginButton => 'Войти';

  @override
  String get registerButton => 'Зарегистрироваться';

  @override
  String get verifyOtpButton => 'Подтвердить';

  @override
  String get resendOtpButton => 'Отправить код снова';

  @override
  String get noAccount => 'Нет аккаунта?';

  @override
  String get hasAccount => 'Уже есть аккаунт?';

  @override
  String get goToRegister => 'Зарегистрироваться';

  @override
  String get goToLogin => 'Войти';

  @override
  String get displayNameLabel => 'Отображаемое имя';

  @override
  String get bioLabel => 'О себе';

  @override
  String get saveButton => 'Сохранить';

  @override
  String get logoutButton => 'Выйти';

  @override
  String get localeLabel => 'Язык';

  @override
  String get darkModeLabel => 'Тёмная тема';

  @override
  String get systemTheme => 'Системная';

  @override
  String get lightTheme => 'Светлая';

  @override
  String get darkTheme => 'Тёмная';

  @override
  String get homeTab => 'Главная';

  @override
  String get profileTab => 'Профиль';

  @override
  String get settingsTab => 'Настройки';

  @override
  String get authRequired => 'Войдите, чтобы продолжить';
}
