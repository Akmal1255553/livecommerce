// ignore: unused_import
import 'package:intl/intl.dart' as intl;
import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for Uzbek (`uz`).
class AppLocalizationsUz extends AppLocalizations {
  AppLocalizationsUz([String locale = 'uz']) : super(locale);

  @override
  String get appTitle => 'LiveCommerce';

  @override
  String get loginTitle => 'Kirish';

  @override
  String get registerTitle => 'Ro\'yxatdan o\'tish';

  @override
  String get otpTitle => 'Telefonni tasdiqlash';

  @override
  String get profileTitle => 'Profil';

  @override
  String get settingsTitle => 'Sozlamalar';

  @override
  String get emailLabel => 'Email';

  @override
  String get phoneLabel => 'Telefon';

  @override
  String get usernameLabel => 'Foydalanuvchi nomi';

  @override
  String get passwordLabel => 'Parol';

  @override
  String get passwordConfirmLabel => 'Parolni tasdiqlash';

  @override
  String get loginFieldLabel => 'Email, telefon yoki username';

  @override
  String get otpLabel => 'SMS kod';

  @override
  String get loginButton => 'Kirish';

  @override
  String get registerButton => 'Ro\'yxatdan o\'tish';

  @override
  String get verifyOtpButton => 'Tasdiqlash';

  @override
  String get resendOtpButton => 'Kodni qayta yuborish';

  @override
  String get noAccount => 'Hisobingiz yo\'qmi?';

  @override
  String get hasAccount => 'Hisobingiz bormi?';

  @override
  String get goToRegister => 'Ro\'yxatdan o\'ting';

  @override
  String get goToLogin => 'Kiring';

  @override
  String get displayNameLabel => 'Ko\'rinadigan ism';

  @override
  String get bioLabel => 'Bio';

  @override
  String get saveButton => 'Saqlash';

  @override
  String get logoutButton => 'Chiqish';

  @override
  String get localeLabel => 'Til';

  @override
  String get darkModeLabel => 'Qorong\'u rejim';

  @override
  String get systemTheme => 'Tizim';

  @override
  String get lightTheme => 'Yorug\'';

  @override
  String get darkTheme => 'Qorong\'u';

  @override
  String get homeTab => 'Bosh sahifa';

  @override
  String get profileTab => 'Profil';

  @override
  String get settingsTab => 'Sozlamalar';

  @override
  String get authRequired => 'Davom etish uchun tizimga kiring';

  @override
  String get retry => 'Qayta urinish';

  @override
  String get refresh => 'Yangilash';

  @override
  String get browseFeed => 'Lentuaga o\'tish';

  @override
  String get requiredField => 'Majburiy';

  @override
  String get offlineBanner =>
      'Internet yo\'q. Ba\'zi amallar ishlamasligi mumkin.';

  @override
  String get myOrders => 'Buyurtmalarim';

  @override
  String get sellerCenter => 'Sotuvchi paneli';

  @override
  String get becomeSeller => 'Sotuvchi bo\'lish';

  @override
  String get feedForYou => 'Siz uchun';

  @override
  String get feedFollowing => 'Obunalar';

  @override
  String get liveNowTooltip => 'Jonli efir';

  @override
  String get feedEmptyTitle => 'Hali video yo\'q';

  @override
  String get feedEmptySubtitle => 'Tez orada yangi kontent chiqadi.';

  @override
  String get feedFollowingEmptyTitle =>
      'Videolarni ko\'rish uchun ijodkorlarga obuna bo\'ling';

  @override
  String get feedFollowingEmptySubtitle =>
      'Obuna bo\'lganlaringizning postlari shu yerda chiqadi.';

  @override
  String get saveAction => 'Saqlash';

  @override
  String get bookmarkSaved => 'Saqlanganlarga qo\'shildi';

  @override
  String get bookmarkRemoved => 'Saqlanganlardan olib tashlandi';

  @override
  String get commentsTitle => 'Izohlar';

  @override
  String get commentsHint => 'Izoh yozing…';

  @override
  String get commentsLoading => 'Izohlar yuklanmoqda…';

  @override
  String get commentsEmptyTitle => 'Hali izohlar yo\'q';

  @override
  String get commentsEmptySubtitle => 'Birinchi bo\'lib yozing.';

  @override
  String get cartTitle => 'Savat';

  @override
  String get cartEmptyTitle => 'Savat bo\'sh';

  @override
  String get cartEmptySubtitle =>
      'Lentadan mahsulot tanlab shu yerga qo\'shing.';

  @override
  String get remove => 'O\'chirish';

  @override
  String get subtotal => 'Oraliq summa';

  @override
  String get checkout => 'Buyurtma berish';

  @override
  String get cartIsEmpty => 'Savat bo\'sh';

  @override
  String get checkoutTitle => 'Buyurtma';

  @override
  String get orderSummary => 'Buyurtma xulosasi';

  @override
  String itemCountLabel(int count) {
    return '$count ta mahsulot';
  }

  @override
  String subtotalWithAmount(String amount) {
    return 'Oraliq: $amount';
  }

  @override
  String shippingEstimate(String amount) {
    return 'Yetkazib berish (taxminiy): $amount';
  }

  @override
  String get paymentMethod => 'To\'lov usuli';

  @override
  String get shippingAddress => 'Yetkazib berish manzili';

  @override
  String get fullNameLabel => 'To\'liq ism';

  @override
  String get regionLabel => 'Viloyat';

  @override
  String get cityLabel => 'Shahar';

  @override
  String get addressLabel => 'Manzil';

  @override
  String get postalCodeLabel => 'Pochta indeksi';

  @override
  String get placeOrder => 'Buyurtmani tasdiqlash';

  @override
  String get ordersTitle => 'Buyurtmalarim';

  @override
  String get ordersEmptyTitle => 'Buyurtmalar yo\'q';

  @override
  String get ordersEmptySubtitle =>
      'Checkout qilganingizdan keyin buyurtmalar shu yerda chiqadi.';

  @override
  String get orderDetailsTitle => 'Buyurtma tafsilotlari';

  @override
  String orderStatusLabel(String status) {
    return 'Holat: $status';
  }

  @override
  String orderPaymentLabel(String status) {
    return 'To\'lov: $status';
  }

  @override
  String orderMethodLabel(String method) {
    return 'Usul: $method';
  }

  @override
  String orderPlacedLabel(String date) {
    return 'Berilgan: $date';
  }

  @override
  String get continuePayment => 'To\'lovni davom ettirish';

  @override
  String get requestRefund => 'Qaytarish so\'rash';

  @override
  String get orderItems => 'Mahsulotlar';

  @override
  String get shipping => 'Yetkazib berish';

  @override
  String get discount => 'Chegirma';

  @override
  String get tax => 'Soliq';

  @override
  String get total => 'Jami';

  @override
  String get orderPlacedTitle => 'Buyurtma qabul qilindi';

  @override
  String get thankYou => 'Rahmat!';

  @override
  String orderNumberLabel(String number) {
    return 'Buyurtma #$number';
  }

  @override
  String totalWithAmount(String amount) {
    return 'Jami: $amount';
  }

  @override
  String get viewOrder => 'Buyurtmani ko\'rish';

  @override
  String get viewOrders => 'Buyurtmalarni ko\'rish';

  @override
  String get backToFeed => 'Lentuaga qaytish';

  @override
  String get paymentTitle => 'To\'lov';

  @override
  String payForOrder(String number) {
    return 'Buyurtma #$number uchun to\'lov';
  }

  @override
  String amountLabel(String amount) {
    return 'Summa: $amount';
  }

  @override
  String methodWithValue(String method) {
    return 'Usul: $method';
  }

  @override
  String get paymentSandboxHint =>
      'Bu lokal to\'lov sandbox. Muvaffaqiyatli Click/Payme webhookni simulyatsiya qilish uchun tasdiqlang.';

  @override
  String paymentRedirectLabel(String url) {
    return 'Gateway redirect (sandbox):\n$url';
  }

  @override
  String get payNow => 'To\'lash';

  @override
  String get cancelPayment => 'To\'lovni bekor qilish';

  @override
  String get refundTitle => 'Qaytarish so\'rovi';

  @override
  String get refundIntro =>
      'Nima uchun qaytarish kerakligini sotuvchiga yozing.';

  @override
  String get refundReasonLabel => 'Sabab';

  @override
  String get submitRefund => 'So\'rovni yuborish';

  @override
  String get refundReasonTooShort => 'Sababni yozing (kamida 5 belgi)';

  @override
  String get refundRequested => 'Qaytarish so\'rovi yuborildi';

  @override
  String get productTitle => 'Mahsulot';

  @override
  String get variants => 'Variantlar';

  @override
  String get quantity => 'Miqdor';

  @override
  String get addToCart => 'Savatga qo\'shish';

  @override
  String get outOfStock => 'Mavjud emas';

  @override
  String get selectVariant => 'Variantni tanlang';

  @override
  String get addedToCart => 'Savatga qo\'shildi';

  @override
  String get viewCart => 'Savatni ko\'rish';

  @override
  String get liveTitle => 'Jonli';

  @override
  String get liveNowTab => 'Hozir jonli';

  @override
  String get replaysTab => 'Takrorlar';

  @override
  String get liveEmpty => 'Hozir jonli efir yo\'q';

  @override
  String get replaysEmpty =>
      'Hali takrorlar yo\'q — efir tugagach paydo bo\'ladi';

  @override
  String get liveBadge => 'LIVE';

  @override
  String watchingCount(String username, int count) {
    return '@$username · $count tomosha qilmoqda';
  }

  @override
  String replayDuration(String username, int mins) {
    return '@$username · $mins daqiqa takror';
  }

  @override
  String get goLiveTitle => 'Efirga chiqish';

  @override
  String get goLiveChromeHint =>
      'Chrome MVP da LIVE placeholder ishlatiladi (Agora kamera hali yo\'q).';

  @override
  String get streamTitleLabel => 'Efir nomi';

  @override
  String get attachProducts => 'Mahsulotlarni biriktirish (ixtiyoriy)';

  @override
  String get noProductsYet =>
      'Hali mahsulot yo\'q — baribir efirga chiqishingiz mumkin.';

  @override
  String get startLive => 'Efirni boshlash';

  @override
  String get enterTitle => 'Sarlavha kiriting';

  @override
  String get defaultLiveTitle => 'Jonli sotuv';

  @override
  String get liveRoomTitle => 'Jonli efir';

  @override
  String get livePlaceholderTitle => 'Jonli placeholder';

  @override
  String get livePlaceholderBody =>
      'Agora kamera keyingi bosqichda qo\'shiladi';

  @override
  String get chatHint => 'Biror narsa yozing…';

  @override
  String get endLive => 'Tugatish';

  @override
  String get assistantTooltip => 'Yordamchi';

  @override
  String get noProductsToPin => 'Pin qilish uchun mahsulot yo\'q';

  @override
  String get pinProductTitle => 'Mahsulotni pin qilish';

  @override
  String get liveAssistantTitle => 'Jonli yordamchi';

  @override
  String get liveAssistantBody =>
      'Qoida asosidagi maslahatlar (ADR-012). OpenAI Sprint 9+ da.';

  @override
  String get noSuggestions => 'Hozircha taklif yo\'q';

  @override
  String get pinAction => 'Pin';

  @override
  String get useAction => 'Ishlatish';

  @override
  String get productPinned => 'Mahsulot pin qilindi';

  @override
  String get productPinFailed => 'Mahsulotni pin qilib bo\'lmadi';

  @override
  String get replyDrafted => 'Javob chat maydoniga yozildi';

  @override
  String get replayTitle => 'Takror';

  @override
  String get replayPlaceholder => 'Takror placeholder';

  @override
  String get noReplayUrl => 'Takror URL yo\'q';
}
