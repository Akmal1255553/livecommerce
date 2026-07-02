import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:intl/intl.dart' as intl;

import 'app_localizations_ru.dart';
import 'app_localizations_uz.dart';

// ignore_for_file: type=lint

/// Callers can lookup localized strings with an instance of AppLocalizations
/// returned by `AppLocalizations.of(context)`.
///
/// Applications need to include `AppLocalizations.delegate()` in their app's
/// `localizationDelegates` list, and the locales they support in the app's
/// `supportedLocales` list. For example:
///
/// ```dart
/// import 'l10n/app_localizations.dart';
///
/// return MaterialApp(
///   localizationsDelegates: AppLocalizations.localizationsDelegates,
///   supportedLocales: AppLocalizations.supportedLocales,
///   home: MyApplicationHome(),
/// );
/// ```
///
/// ## Update pubspec.yaml
///
/// Please make sure to update your pubspec.yaml to include the following
/// packages:
///
/// ```yaml
/// dependencies:
///   # Internationalization support.
///   flutter_localizations:
///     sdk: flutter
///   intl: any # Use the pinned version from flutter_localizations
///
///   # Rest of dependencies
/// ```
///
/// ## iOS Applications
///
/// iOS applications define key application metadata, including supported
/// locales, in an Info.plist file that is built into the application bundle.
/// To configure the locales supported by your app, you’ll need to edit this
/// file.
///
/// First, open your project’s ios/Runner.xcworkspace Xcode workspace file.
/// Then, in the Project Navigator, open the Info.plist file under the Runner
/// project’s Runner folder.
///
/// Next, select the Information Property List item, select Add Item from the
/// Editor menu, then select Localizations from the pop-up menu.
///
/// Select and expand the newly-created Localizations item then, for each
/// locale your application supports, add a new item and select the locale
/// you wish to add from the pop-up menu in the Value field. This list should
/// be consistent with the languages listed in the AppLocalizations.supportedLocales
/// property.
abstract class AppLocalizations {
  AppLocalizations(String locale)
    : localeName = intl.Intl.canonicalizedLocale(locale.toString());

  final String localeName;

  static AppLocalizations? of(BuildContext context) {
    return Localizations.of<AppLocalizations>(context, AppLocalizations);
  }

  static const LocalizationsDelegate<AppLocalizations> delegate =
      _AppLocalizationsDelegate();

  /// A list of this localizations delegate along with the default localizations
  /// delegates.
  ///
  /// Returns a list of localizations delegates containing this delegate along with
  /// GlobalMaterialLocalizations.delegate, GlobalCupertinoLocalizations.delegate,
  /// and GlobalWidgetsLocalizations.delegate.
  ///
  /// Additional delegates can be added by appending to this list in
  /// MaterialApp. This list does not have to be used at all if a custom list
  /// of delegates is preferred or required.
  static const List<LocalizationsDelegate<dynamic>> localizationsDelegates =
      <LocalizationsDelegate<dynamic>>[
        delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
      ];

  /// A list of this localizations delegate's supported locales.
  static const List<Locale> supportedLocales = <Locale>[
    Locale('ru'),
    Locale('uz'),
  ];

  /// No description provided for @appTitle.
  ///
  /// In uz, this message translates to:
  /// **'LiveCommerce'**
  String get appTitle;

  /// No description provided for @loginTitle.
  ///
  /// In uz, this message translates to:
  /// **'Kirish'**
  String get loginTitle;

  /// No description provided for @registerTitle.
  ///
  /// In uz, this message translates to:
  /// **'Ro\'yxatdan o\'tish'**
  String get registerTitle;

  /// No description provided for @otpTitle.
  ///
  /// In uz, this message translates to:
  /// **'Telefonni tasdiqlash'**
  String get otpTitle;

  /// No description provided for @profileTitle.
  ///
  /// In uz, this message translates to:
  /// **'Profil'**
  String get profileTitle;

  /// No description provided for @settingsTitle.
  ///
  /// In uz, this message translates to:
  /// **'Sozlamalar'**
  String get settingsTitle;

  /// No description provided for @emailLabel.
  ///
  /// In uz, this message translates to:
  /// **'Email'**
  String get emailLabel;

  /// No description provided for @phoneLabel.
  ///
  /// In uz, this message translates to:
  /// **'Telefon'**
  String get phoneLabel;

  /// No description provided for @usernameLabel.
  ///
  /// In uz, this message translates to:
  /// **'Foydalanuvchi nomi'**
  String get usernameLabel;

  /// No description provided for @passwordLabel.
  ///
  /// In uz, this message translates to:
  /// **'Parol'**
  String get passwordLabel;

  /// No description provided for @passwordConfirmLabel.
  ///
  /// In uz, this message translates to:
  /// **'Parolni tasdiqlash'**
  String get passwordConfirmLabel;

  /// No description provided for @loginFieldLabel.
  ///
  /// In uz, this message translates to:
  /// **'Email, telefon yoki username'**
  String get loginFieldLabel;

  /// No description provided for @otpLabel.
  ///
  /// In uz, this message translates to:
  /// **'SMS kod'**
  String get otpLabel;

  /// No description provided for @loginButton.
  ///
  /// In uz, this message translates to:
  /// **'Kirish'**
  String get loginButton;

  /// No description provided for @registerButton.
  ///
  /// In uz, this message translates to:
  /// **'Ro\'yxatdan o\'tish'**
  String get registerButton;

  /// No description provided for @verifyOtpButton.
  ///
  /// In uz, this message translates to:
  /// **'Tasdiqlash'**
  String get verifyOtpButton;

  /// No description provided for @resendOtpButton.
  ///
  /// In uz, this message translates to:
  /// **'Kodni qayta yuborish'**
  String get resendOtpButton;

  /// No description provided for @noAccount.
  ///
  /// In uz, this message translates to:
  /// **'Hisobingiz yo\'qmi?'**
  String get noAccount;

  /// No description provided for @hasAccount.
  ///
  /// In uz, this message translates to:
  /// **'Hisobingiz bormi?'**
  String get hasAccount;

  /// No description provided for @goToRegister.
  ///
  /// In uz, this message translates to:
  /// **'Ro\'yxatdan o\'ting'**
  String get goToRegister;

  /// No description provided for @goToLogin.
  ///
  /// In uz, this message translates to:
  /// **'Kiring'**
  String get goToLogin;

  /// No description provided for @displayNameLabel.
  ///
  /// In uz, this message translates to:
  /// **'Ko\'rinadigan ism'**
  String get displayNameLabel;

  /// No description provided for @bioLabel.
  ///
  /// In uz, this message translates to:
  /// **'Bio'**
  String get bioLabel;

  /// No description provided for @saveButton.
  ///
  /// In uz, this message translates to:
  /// **'Saqlash'**
  String get saveButton;

  /// No description provided for @logoutButton.
  ///
  /// In uz, this message translates to:
  /// **'Chiqish'**
  String get logoutButton;

  /// No description provided for @localeLabel.
  ///
  /// In uz, this message translates to:
  /// **'Til'**
  String get localeLabel;

  /// No description provided for @darkModeLabel.
  ///
  /// In uz, this message translates to:
  /// **'Qorong\'u rejim'**
  String get darkModeLabel;

  /// No description provided for @systemTheme.
  ///
  /// In uz, this message translates to:
  /// **'Tizim'**
  String get systemTheme;

  /// No description provided for @lightTheme.
  ///
  /// In uz, this message translates to:
  /// **'Yorug\''**
  String get lightTheme;

  /// No description provided for @darkTheme.
  ///
  /// In uz, this message translates to:
  /// **'Qorong\'u'**
  String get darkTheme;

  /// No description provided for @homeTab.
  ///
  /// In uz, this message translates to:
  /// **'Bosh sahifa'**
  String get homeTab;

  /// No description provided for @profileTab.
  ///
  /// In uz, this message translates to:
  /// **'Profil'**
  String get profileTab;

  /// No description provided for @settingsTab.
  ///
  /// In uz, this message translates to:
  /// **'Sozlamalar'**
  String get settingsTab;

  /// No description provided for @authRequired.
  ///
  /// In uz, this message translates to:
  /// **'Davom etish uchun tizimga kiring'**
  String get authRequired;
}

class _AppLocalizationsDelegate
    extends LocalizationsDelegate<AppLocalizations> {
  const _AppLocalizationsDelegate();

  @override
  Future<AppLocalizations> load(Locale locale) {
    return SynchronousFuture<AppLocalizations>(lookupAppLocalizations(locale));
  }

  @override
  bool isSupported(Locale locale) =>
      <String>['ru', 'uz'].contains(locale.languageCode);

  @override
  bool shouldReload(_AppLocalizationsDelegate old) => false;
}

AppLocalizations lookupAppLocalizations(Locale locale) {
  // Lookup logic when only language code is specified.
  switch (locale.languageCode) {
    case 'ru':
      return AppLocalizationsRu();
    case 'uz':
      return AppLocalizationsUz();
  }

  throw FlutterError(
    'AppLocalizations.delegate failed to load unsupported locale "$locale". This is likely '
    'an issue with the localizations generation tool. Please file an issue '
    'on GitHub with a reproducible sample app and the gen-l10n configuration '
    'that was used.',
  );
}
