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

  /// No description provided for @retry.
  ///
  /// In uz, this message translates to:
  /// **'Qayta urinish'**
  String get retry;

  /// No description provided for @refresh.
  ///
  /// In uz, this message translates to:
  /// **'Yangilash'**
  String get refresh;

  /// No description provided for @browseFeed.
  ///
  /// In uz, this message translates to:
  /// **'Lentuaga o\'tish'**
  String get browseFeed;

  /// No description provided for @requiredField.
  ///
  /// In uz, this message translates to:
  /// **'Majburiy'**
  String get requiredField;

  /// No description provided for @offlineBanner.
  ///
  /// In uz, this message translates to:
  /// **'Internet yo\'q. Ba\'zi amallar ishlamasligi mumkin.'**
  String get offlineBanner;

  /// No description provided for @myOrders.
  ///
  /// In uz, this message translates to:
  /// **'Buyurtmalarim'**
  String get myOrders;

  /// No description provided for @sellerCenter.
  ///
  /// In uz, this message translates to:
  /// **'Sotuvchi paneli'**
  String get sellerCenter;

  /// No description provided for @becomeSeller.
  ///
  /// In uz, this message translates to:
  /// **'Sotuvchi bo\'lish'**
  String get becomeSeller;

  /// No description provided for @feedForYou.
  ///
  /// In uz, this message translates to:
  /// **'Siz uchun'**
  String get feedForYou;

  /// No description provided for @feedFollowing.
  ///
  /// In uz, this message translates to:
  /// **'Obunalar'**
  String get feedFollowing;

  /// No description provided for @liveNowTooltip.
  ///
  /// In uz, this message translates to:
  /// **'Jonli efir'**
  String get liveNowTooltip;

  /// No description provided for @feedEmptyTitle.
  ///
  /// In uz, this message translates to:
  /// **'Hali video yo\'q'**
  String get feedEmptyTitle;

  /// No description provided for @feedEmptySubtitle.
  ///
  /// In uz, this message translates to:
  /// **'Tez orada yangi kontent chiqadi.'**
  String get feedEmptySubtitle;

  /// No description provided for @feedFollowingEmptyTitle.
  ///
  /// In uz, this message translates to:
  /// **'Videolarni ko\'rish uchun ijodkorlarga obuna bo\'ling'**
  String get feedFollowingEmptyTitle;

  /// No description provided for @feedFollowingEmptySubtitle.
  ///
  /// In uz, this message translates to:
  /// **'Obuna bo\'lganlaringizning postlari shu yerda chiqadi.'**
  String get feedFollowingEmptySubtitle;

  /// No description provided for @saveAction.
  ///
  /// In uz, this message translates to:
  /// **'Saqlash'**
  String get saveAction;

  /// No description provided for @bookmarkSaved.
  ///
  /// In uz, this message translates to:
  /// **'Saqlanganlarga qo\'shildi'**
  String get bookmarkSaved;

  /// No description provided for @bookmarkRemoved.
  ///
  /// In uz, this message translates to:
  /// **'Saqlanganlardan olib tashlandi'**
  String get bookmarkRemoved;

  /// No description provided for @commentsTitle.
  ///
  /// In uz, this message translates to:
  /// **'Izohlar'**
  String get commentsTitle;

  /// No description provided for @commentsHint.
  ///
  /// In uz, this message translates to:
  /// **'Izoh yozing…'**
  String get commentsHint;

  /// No description provided for @commentsLoading.
  ///
  /// In uz, this message translates to:
  /// **'Izohlar yuklanmoqda…'**
  String get commentsLoading;

  /// No description provided for @commentsEmptyTitle.
  ///
  /// In uz, this message translates to:
  /// **'Hali izohlar yo\'q'**
  String get commentsEmptyTitle;

  /// No description provided for @commentsEmptySubtitle.
  ///
  /// In uz, this message translates to:
  /// **'Birinchi bo\'lib yozing.'**
  String get commentsEmptySubtitle;

  /// No description provided for @cartTitle.
  ///
  /// In uz, this message translates to:
  /// **'Savat'**
  String get cartTitle;

  /// No description provided for @cartEmptyTitle.
  ///
  /// In uz, this message translates to:
  /// **'Savat bo\'sh'**
  String get cartEmptyTitle;

  /// No description provided for @cartEmptySubtitle.
  ///
  /// In uz, this message translates to:
  /// **'Lentadan mahsulot tanlab shu yerga qo\'shing.'**
  String get cartEmptySubtitle;

  /// No description provided for @remove.
  ///
  /// In uz, this message translates to:
  /// **'O\'chirish'**
  String get remove;

  /// No description provided for @subtotal.
  ///
  /// In uz, this message translates to:
  /// **'Oraliq summa'**
  String get subtotal;

  /// No description provided for @checkout.
  ///
  /// In uz, this message translates to:
  /// **'Buyurtma berish'**
  String get checkout;

  /// No description provided for @cartIsEmpty.
  ///
  /// In uz, this message translates to:
  /// **'Savat bo\'sh'**
  String get cartIsEmpty;

  /// No description provided for @checkoutTitle.
  ///
  /// In uz, this message translates to:
  /// **'Buyurtma'**
  String get checkoutTitle;

  /// No description provided for @orderSummary.
  ///
  /// In uz, this message translates to:
  /// **'Buyurtma xulosasi'**
  String get orderSummary;

  /// No description provided for @itemCountLabel.
  ///
  /// In uz, this message translates to:
  /// **'{count} ta mahsulot'**
  String itemCountLabel(int count);

  /// No description provided for @subtotalWithAmount.
  ///
  /// In uz, this message translates to:
  /// **'Oraliq: {amount}'**
  String subtotalWithAmount(String amount);

  /// No description provided for @shippingEstimate.
  ///
  /// In uz, this message translates to:
  /// **'Yetkazib berish (taxminiy): {amount}'**
  String shippingEstimate(String amount);

  /// No description provided for @paymentMethod.
  ///
  /// In uz, this message translates to:
  /// **'To\'lov usuli'**
  String get paymentMethod;

  /// No description provided for @shippingAddress.
  ///
  /// In uz, this message translates to:
  /// **'Yetkazib berish manzili'**
  String get shippingAddress;

  /// No description provided for @fullNameLabel.
  ///
  /// In uz, this message translates to:
  /// **'To\'liq ism'**
  String get fullNameLabel;

  /// No description provided for @regionLabel.
  ///
  /// In uz, this message translates to:
  /// **'Viloyat'**
  String get regionLabel;

  /// No description provided for @cityLabel.
  ///
  /// In uz, this message translates to:
  /// **'Shahar'**
  String get cityLabel;

  /// No description provided for @addressLabel.
  ///
  /// In uz, this message translates to:
  /// **'Manzil'**
  String get addressLabel;

  /// No description provided for @postalCodeLabel.
  ///
  /// In uz, this message translates to:
  /// **'Pochta indeksi'**
  String get postalCodeLabel;

  /// No description provided for @placeOrder.
  ///
  /// In uz, this message translates to:
  /// **'Buyurtmani tasdiqlash'**
  String get placeOrder;

  /// No description provided for @ordersTitle.
  ///
  /// In uz, this message translates to:
  /// **'Buyurtmalarim'**
  String get ordersTitle;

  /// No description provided for @ordersEmptyTitle.
  ///
  /// In uz, this message translates to:
  /// **'Buyurtmalar yo\'q'**
  String get ordersEmptyTitle;

  /// No description provided for @ordersEmptySubtitle.
  ///
  /// In uz, this message translates to:
  /// **'Checkout qilganingizdan keyin buyurtmalar shu yerda chiqadi.'**
  String get ordersEmptySubtitle;

  /// No description provided for @orderDetailsTitle.
  ///
  /// In uz, this message translates to:
  /// **'Buyurtma tafsilotlari'**
  String get orderDetailsTitle;

  /// No description provided for @orderStatusLabel.
  ///
  /// In uz, this message translates to:
  /// **'Holat: {status}'**
  String orderStatusLabel(String status);

  /// No description provided for @orderPaymentLabel.
  ///
  /// In uz, this message translates to:
  /// **'To\'lov: {status}'**
  String orderPaymentLabel(String status);

  /// No description provided for @orderMethodLabel.
  ///
  /// In uz, this message translates to:
  /// **'Usul: {method}'**
  String orderMethodLabel(String method);

  /// No description provided for @orderPlacedLabel.
  ///
  /// In uz, this message translates to:
  /// **'Berilgan: {date}'**
  String orderPlacedLabel(String date);

  /// No description provided for @continuePayment.
  ///
  /// In uz, this message translates to:
  /// **'To\'lovni davom ettirish'**
  String get continuePayment;

  /// No description provided for @requestRefund.
  ///
  /// In uz, this message translates to:
  /// **'Qaytarish so\'rash'**
  String get requestRefund;

  /// No description provided for @orderItems.
  ///
  /// In uz, this message translates to:
  /// **'Mahsulotlar'**
  String get orderItems;

  /// No description provided for @shipping.
  ///
  /// In uz, this message translates to:
  /// **'Yetkazib berish'**
  String get shipping;

  /// No description provided for @discount.
  ///
  /// In uz, this message translates to:
  /// **'Chegirma'**
  String get discount;

  /// No description provided for @tax.
  ///
  /// In uz, this message translates to:
  /// **'Soliq'**
  String get tax;

  /// No description provided for @total.
  ///
  /// In uz, this message translates to:
  /// **'Jami'**
  String get total;

  /// No description provided for @orderPlacedTitle.
  ///
  /// In uz, this message translates to:
  /// **'Buyurtma qabul qilindi'**
  String get orderPlacedTitle;

  /// No description provided for @thankYou.
  ///
  /// In uz, this message translates to:
  /// **'Rahmat!'**
  String get thankYou;

  /// No description provided for @orderNumberLabel.
  ///
  /// In uz, this message translates to:
  /// **'Buyurtma #{number}'**
  String orderNumberLabel(String number);

  /// No description provided for @totalWithAmount.
  ///
  /// In uz, this message translates to:
  /// **'Jami: {amount}'**
  String totalWithAmount(String amount);

  /// No description provided for @viewOrder.
  ///
  /// In uz, this message translates to:
  /// **'Buyurtmani ko\'rish'**
  String get viewOrder;

  /// No description provided for @viewOrders.
  ///
  /// In uz, this message translates to:
  /// **'Buyurtmalarni ko\'rish'**
  String get viewOrders;

  /// No description provided for @backToFeed.
  ///
  /// In uz, this message translates to:
  /// **'Lentuaga qaytish'**
  String get backToFeed;

  /// No description provided for @paymentTitle.
  ///
  /// In uz, this message translates to:
  /// **'To\'lov'**
  String get paymentTitle;

  /// No description provided for @payForOrder.
  ///
  /// In uz, this message translates to:
  /// **'Buyurtma #{number} uchun to\'lov'**
  String payForOrder(String number);

  /// No description provided for @amountLabel.
  ///
  /// In uz, this message translates to:
  /// **'Summa: {amount}'**
  String amountLabel(String amount);

  /// No description provided for @methodWithValue.
  ///
  /// In uz, this message translates to:
  /// **'Usul: {method}'**
  String methodWithValue(String method);

  /// No description provided for @paymentSandboxHint.
  ///
  /// In uz, this message translates to:
  /// **'Bu lokal to\'lov sandbox. Muvaffaqiyatli Click/Payme webhookni simulyatsiya qilish uchun tasdiqlang.'**
  String get paymentSandboxHint;

  /// No description provided for @paymentRedirectLabel.
  ///
  /// In uz, this message translates to:
  /// **'Gateway redirect (sandbox):\n{url}'**
  String paymentRedirectLabel(String url);

  /// No description provided for @payNow.
  ///
  /// In uz, this message translates to:
  /// **'To\'lash'**
  String get payNow;

  /// No description provided for @cancelPayment.
  ///
  /// In uz, this message translates to:
  /// **'To\'lovni bekor qilish'**
  String get cancelPayment;

  /// No description provided for @refundTitle.
  ///
  /// In uz, this message translates to:
  /// **'Qaytarish so\'rovi'**
  String get refundTitle;

  /// No description provided for @refundIntro.
  ///
  /// In uz, this message translates to:
  /// **'Nima uchun qaytarish kerakligini sotuvchiga yozing.'**
  String get refundIntro;

  /// No description provided for @refundReasonLabel.
  ///
  /// In uz, this message translates to:
  /// **'Sabab'**
  String get refundReasonLabel;

  /// No description provided for @submitRefund.
  ///
  /// In uz, this message translates to:
  /// **'So\'rovni yuborish'**
  String get submitRefund;

  /// No description provided for @refundReasonTooShort.
  ///
  /// In uz, this message translates to:
  /// **'Sababni yozing (kamida 5 belgi)'**
  String get refundReasonTooShort;

  /// No description provided for @refundRequested.
  ///
  /// In uz, this message translates to:
  /// **'Qaytarish so\'rovi yuborildi'**
  String get refundRequested;

  /// No description provided for @productTitle.
  ///
  /// In uz, this message translates to:
  /// **'Mahsulot'**
  String get productTitle;

  /// No description provided for @variants.
  ///
  /// In uz, this message translates to:
  /// **'Variantlar'**
  String get variants;

  /// No description provided for @quantity.
  ///
  /// In uz, this message translates to:
  /// **'Miqdor'**
  String get quantity;

  /// No description provided for @addToCart.
  ///
  /// In uz, this message translates to:
  /// **'Savatga qo\'shish'**
  String get addToCart;

  /// No description provided for @outOfStock.
  ///
  /// In uz, this message translates to:
  /// **'Mavjud emas'**
  String get outOfStock;

  /// No description provided for @selectVariant.
  ///
  /// In uz, this message translates to:
  /// **'Variantni tanlang'**
  String get selectVariant;

  /// No description provided for @addedToCart.
  ///
  /// In uz, this message translates to:
  /// **'Savatga qo\'shildi'**
  String get addedToCart;

  /// No description provided for @viewCart.
  ///
  /// In uz, this message translates to:
  /// **'Savatni ko\'rish'**
  String get viewCart;

  /// No description provided for @liveTitle.
  ///
  /// In uz, this message translates to:
  /// **'Jonli'**
  String get liveTitle;

  /// No description provided for @liveNowTab.
  ///
  /// In uz, this message translates to:
  /// **'Hozir jonli'**
  String get liveNowTab;

  /// No description provided for @replaysTab.
  ///
  /// In uz, this message translates to:
  /// **'Takrorlar'**
  String get replaysTab;

  /// No description provided for @liveEmpty.
  ///
  /// In uz, this message translates to:
  /// **'Hozir jonli efir yo\'q'**
  String get liveEmpty;

  /// No description provided for @replaysEmpty.
  ///
  /// In uz, this message translates to:
  /// **'Hali takrorlar yo\'q — efir tugagach paydo bo\'ladi'**
  String get replaysEmpty;

  /// No description provided for @liveBadge.
  ///
  /// In uz, this message translates to:
  /// **'LIVE'**
  String get liveBadge;

  /// No description provided for @watchingCount.
  ///
  /// In uz, this message translates to:
  /// **'@{username} · {count} tomosha qilmoqda'**
  String watchingCount(String username, int count);

  /// No description provided for @replayDuration.
  ///
  /// In uz, this message translates to:
  /// **'@{username} · {mins} daqiqa takror'**
  String replayDuration(String username, int mins);

  /// No description provided for @goLiveTitle.
  ///
  /// In uz, this message translates to:
  /// **'Efirga chiqish'**
  String get goLiveTitle;

  /// No description provided for @goLiveChromeHint.
  ///
  /// In uz, this message translates to:
  /// **'Chrome MVP da LIVE placeholder ishlatiladi (Agora kamera hali yo\'q).'**
  String get goLiveChromeHint;

  /// No description provided for @streamTitleLabel.
  ///
  /// In uz, this message translates to:
  /// **'Efir nomi'**
  String get streamTitleLabel;

  /// No description provided for @attachProducts.
  ///
  /// In uz, this message translates to:
  /// **'Mahsulotlarni biriktirish (ixtiyoriy)'**
  String get attachProducts;

  /// No description provided for @noProductsYet.
  ///
  /// In uz, this message translates to:
  /// **'Hali mahsulot yo\'q — baribir efirga chiqishingiz mumkin.'**
  String get noProductsYet;

  /// No description provided for @startLive.
  ///
  /// In uz, this message translates to:
  /// **'Efirni boshlash'**
  String get startLive;

  /// No description provided for @enterTitle.
  ///
  /// In uz, this message translates to:
  /// **'Sarlavha kiriting'**
  String get enterTitle;

  /// No description provided for @defaultLiveTitle.
  ///
  /// In uz, this message translates to:
  /// **'Jonli sotuv'**
  String get defaultLiveTitle;

  /// No description provided for @liveRoomTitle.
  ///
  /// In uz, this message translates to:
  /// **'Jonli efir'**
  String get liveRoomTitle;

  /// No description provided for @livePlaceholderTitle.
  ///
  /// In uz, this message translates to:
  /// **'Jonli placeholder'**
  String get livePlaceholderTitle;

  /// No description provided for @livePlaceholderBody.
  ///
  /// In uz, this message translates to:
  /// **'A/V Android/iOS da STREAMING_PROVIDER=agora bilan ishlaydi'**
  String get livePlaceholderBody;

  /// No description provided for @liveAvNativeOnly.
  ///
  /// In uz, this message translates to:
  /// **'Kamera bilan jonli efir faqat Android/iOS da'**
  String get liveAvNativeOnly;

  /// No description provided for @liveConnecting.
  ///
  /// In uz, this message translates to:
  /// **'Ulanmoqda…'**
  String get liveConnecting;

  /// No description provided for @liveWaitingHost.
  ///
  /// In uz, this message translates to:
  /// **'Boshlovchi kutilmoqda…'**
  String get liveWaitingHost;

  /// No description provided for @chatHint.
  ///
  /// In uz, this message translates to:
  /// **'Biror narsa yozing…'**
  String get chatHint;

  /// No description provided for @endLive.
  ///
  /// In uz, this message translates to:
  /// **'Tugatish'**
  String get endLive;

  /// No description provided for @assistantTooltip.
  ///
  /// In uz, this message translates to:
  /// **'Yordamchi'**
  String get assistantTooltip;

  /// No description provided for @noProductsToPin.
  ///
  /// In uz, this message translates to:
  /// **'Pin qilish uchun mahsulot yo\'q'**
  String get noProductsToPin;

  /// No description provided for @pinProductTitle.
  ///
  /// In uz, this message translates to:
  /// **'Mahsulotni pin qilish'**
  String get pinProductTitle;

  /// No description provided for @liveAssistantTitle.
  ///
  /// In uz, this message translates to:
  /// **'Jonli yordamchi'**
  String get liveAssistantTitle;

  /// No description provided for @liveAssistantBody.
  ///
  /// In uz, this message translates to:
  /// **'Qoida asosidagi maslahatlar (ADR-012). OpenAI Sprint 9+ da.'**
  String get liveAssistantBody;

  /// No description provided for @noSuggestions.
  ///
  /// In uz, this message translates to:
  /// **'Hozircha taklif yo\'q'**
  String get noSuggestions;

  /// No description provided for @pinAction.
  ///
  /// In uz, this message translates to:
  /// **'Pin'**
  String get pinAction;

  /// No description provided for @useAction.
  ///
  /// In uz, this message translates to:
  /// **'Ishlatish'**
  String get useAction;

  /// No description provided for @productPinned.
  ///
  /// In uz, this message translates to:
  /// **'Mahsulot pin qilindi'**
  String get productPinned;

  /// No description provided for @productPinFailed.
  ///
  /// In uz, this message translates to:
  /// **'Mahsulotni pin qilib bo\'lmadi'**
  String get productPinFailed;

  /// No description provided for @replyDrafted.
  ///
  /// In uz, this message translates to:
  /// **'Javob chat maydoniga yozildi'**
  String get replyDrafted;

  /// No description provided for @replayTitle.
  ///
  /// In uz, this message translates to:
  /// **'Takror'**
  String get replayTitle;

  /// No description provided for @replayPlaceholder.
  ///
  /// In uz, this message translates to:
  /// **'Takror placeholder'**
  String get replayPlaceholder;

  /// No description provided for @noReplayUrl.
  ///
  /// In uz, this message translates to:
  /// **'Takror URL yo\'q'**
  String get noReplayUrl;

  /// No description provided for @messagesTitle.
  ///
  /// In uz, this message translates to:
  /// **'Xabarlar'**
  String get messagesTitle;

  /// No description provided for @messageSeller.
  ///
  /// In uz, this message translates to:
  /// **'Sotuvchiga yozish'**
  String get messageSeller;

  /// No description provided for @messageAboutOrder.
  ///
  /// In uz, this message translates to:
  /// **'Buyurtma haqida yozish'**
  String get messageAboutOrder;

  /// No description provided for @noConversationsTitle.
  ///
  /// In uz, this message translates to:
  /// **'Xabarlar yo\'q'**
  String get noConversationsTitle;

  /// No description provided for @noConversationsSubtitle.
  ///
  /// In uz, this message translates to:
  /// **'Sotuvchilar bilan yozishuv bu yerda ko\'rinadi.'**
  String get noConversationsSubtitle;

  /// No description provided for @typeMessageHint.
  ///
  /// In uz, this message translates to:
  /// **'Xabar yozing…'**
  String get typeMessageHint;

  /// No description provided for @sendMessageFailed.
  ///
  /// In uz, this message translates to:
  /// **'Xabar yuborib bo\'lmadi'**
  String get sendMessageFailed;

  /// No description provided for @reportTitle.
  ///
  /// In uz, this message translates to:
  /// **'Shikoyat qilish'**
  String get reportTitle;

  /// No description provided for @reportReasonSpam.
  ///
  /// In uz, this message translates to:
  /// **'Spam'**
  String get reportReasonSpam;

  /// No description provided for @reportReasonInappropriate.
  ///
  /// In uz, this message translates to:
  /// **'Nomaqbul kontent'**
  String get reportReasonInappropriate;

  /// No description provided for @reportReasonScam.
  ///
  /// In uz, this message translates to:
  /// **'Firibgarlik'**
  String get reportReasonScam;

  /// No description provided for @reportReasonOther.
  ///
  /// In uz, this message translates to:
  /// **'Boshqa'**
  String get reportReasonOther;

  /// No description provided for @reportSubmitted.
  ///
  /// In uz, this message translates to:
  /// **'Shikoyat yuborildi'**
  String get reportSubmitted;

  /// No description provided for @reportAction.
  ///
  /// In uz, this message translates to:
  /// **'Shikoyat'**
  String get reportAction;
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
