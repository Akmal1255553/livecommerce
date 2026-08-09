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
  String get cancelButton => 'Отмена';

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

  @override
  String get retry => 'Повторить';

  @override
  String get refresh => 'Обновить';

  @override
  String get browseFeed => 'К ленте';

  @override
  String get requiredField => 'Обязательно';

  @override
  String get offlineBanner =>
      'Нет сети. Некоторые действия могут не сработать.';

  @override
  String get myOrders => 'Мои заказы';

  @override
  String get sellerCenter => 'Кабинет продавца';

  @override
  String get becomeSeller => 'Стать продавцом';

  @override
  String get feedForYou => 'Для вас';

  @override
  String get feedFollowing => 'Подписки';

  @override
  String get liveNowTooltip => 'Прямой эфир';

  @override
  String get feedEmptyTitle => 'Пока нет видео';

  @override
  String get feedEmptySubtitle => 'Скоро появятся новые ролики.';

  @override
  String get feedFollowingEmptyTitle =>
      'Подпишитесь на авторов, чтобы видеть их видео';

  @override
  String get feedFollowingEmptySubtitle =>
      'Посты тех, на кого вы подписаны, появятся здесь.';

  @override
  String get saveAction => 'Сохранить';

  @override
  String get bookmarkSaved => 'Добавлено в закладки';

  @override
  String get bookmarkRemoved => 'Удалено из закладок';

  @override
  String get commentsTitle => 'Комментарии';

  @override
  String get commentsHint => 'Написать комментарий…';

  @override
  String get commentsLoading => 'Загрузка комментариев…';

  @override
  String get commentsEmptyTitle => 'Пока нет комментариев';

  @override
  String get commentsEmptySubtitle => 'Будьте первым.';

  @override
  String get buyNow => 'Купить';

  @override
  String get shareAction => 'Поделиться';

  @override
  String get moreAction => 'Ещё';

  @override
  String get linkCopied => 'Ссылка скопирована';

  @override
  String get emojiPickerTitle => 'Выберите эмодзи';

  @override
  String commentsCount(int count) {
    return '$count комментариев';
  }

  @override
  String get timeJustNow => 'сейчас';

  @override
  String timeMinutesShort(int count) {
    return '$count мин';
  }

  @override
  String timeHoursShort(int count) {
    return '$count ч';
  }

  @override
  String timeDaysShort(int count) {
    return '$count дн';
  }

  @override
  String timeWeeksShort(int count) {
    return '$count нед';
  }

  @override
  String get cartTitle => 'Корзина';

  @override
  String get cartEmptyTitle => 'Корзина пуста';

  @override
  String get cartEmptySubtitle => 'Выберите товар в ленте и добавьте сюда.';

  @override
  String get remove => 'Удалить';

  @override
  String get subtotal => 'Подытог';

  @override
  String get checkout => 'Оформить';

  @override
  String get cartIsEmpty => 'Корзина пуста';

  @override
  String get checkoutTitle => 'Оформление';

  @override
  String get orderSummary => 'Сводка заказа';

  @override
  String itemCountLabel(int count) {
    return '$count товар(ов)';
  }

  @override
  String subtotalWithAmount(String amount) {
    return 'Подытог: $amount';
  }

  @override
  String shippingEstimate(String amount) {
    return 'Доставка (оценка): $amount';
  }

  @override
  String get paymentMethod => 'Способ оплаты';

  @override
  String get shippingAddress => 'Адрес доставки';

  @override
  String get fullNameLabel => 'ФИО';

  @override
  String get regionLabel => 'Регион';

  @override
  String get cityLabel => 'Город';

  @override
  String get addressLabel => 'Адрес';

  @override
  String get postalCodeLabel => 'Индекс';

  @override
  String get placeOrder => 'Подтвердить заказ';

  @override
  String get ordersTitle => 'Мои заказы';

  @override
  String get ordersEmptyTitle => 'Заказов пока нет';

  @override
  String get ordersEmptySubtitle => 'После оформления заказы появятся здесь.';

  @override
  String get orderDetailsTitle => 'Детали заказа';

  @override
  String orderStatusLabel(String status) {
    return 'Статус: $status';
  }

  @override
  String orderPaymentLabel(String status) {
    return 'Оплата: $status';
  }

  @override
  String orderMethodLabel(String method) {
    return 'Метод: $method';
  }

  @override
  String orderPlacedLabel(String date) {
    return 'Оформлен: $date';
  }

  @override
  String get continuePayment => 'Продолжить оплату';

  @override
  String get requestRefund => 'Запросить возврат';

  @override
  String get orderItems => 'Товары';

  @override
  String get shipping => 'Доставка';

  @override
  String get discount => 'Скидка';

  @override
  String get tax => 'Налог';

  @override
  String get total => 'Итого';

  @override
  String get orderPlacedTitle => 'Заказ оформлен';

  @override
  String get thankYou => 'Спасибо!';

  @override
  String orderNumberLabel(String number) {
    return 'Заказ #$number';
  }

  @override
  String totalWithAmount(String amount) {
    return 'Итого: $amount';
  }

  @override
  String get viewOrder => 'Открыть заказ';

  @override
  String get viewOrders => 'К заказам';

  @override
  String get backToFeed => 'К ленте';

  @override
  String get paymentTitle => 'Оплата';

  @override
  String payForOrder(String number) {
    return 'Оплата заказа #$number';
  }

  @override
  String amountLabel(String amount) {
    return 'Сумма: $amount';
  }

  @override
  String methodWithValue(String method) {
    return 'Метод: $method';
  }

  @override
  String get paymentSandboxHint =>
      'Локальный sandbox оплаты. Подтвердите, чтобы симулировать успешный webhook Click/Payme.';

  @override
  String paymentRedirectLabel(String url) {
    return 'Редирект шлюза (sandbox):\n$url';
  }

  @override
  String get payNow => 'Оплатить';

  @override
  String get cancelPayment => 'Отменить оплату';

  @override
  String get refundTitle => 'Запрос возврата';

  @override
  String get refundIntro => 'Опишите продавцу, почему нужен возврат.';

  @override
  String get refundReasonLabel => 'Причина';

  @override
  String get submitRefund => 'Отправить запрос';

  @override
  String get refundReasonTooShort => 'Опишите причину (минимум 5 символов)';

  @override
  String get refundRequested => 'Запрос на возврат отправлен';

  @override
  String get productTitle => 'Товар';

  @override
  String get variants => 'Варианты';

  @override
  String get quantity => 'Количество';

  @override
  String get addToCart => 'В корзину';

  @override
  String get outOfStock => 'Нет в наличии';

  @override
  String get selectVariant => 'Выберите вариант';

  @override
  String get addedToCart => 'Добавлено в корзину';

  @override
  String get viewCart => 'Открыть корзину';

  @override
  String get liveTitle => 'Эфир';

  @override
  String get liveNowTab => 'Сейчас в эфире';

  @override
  String get replaysTab => 'Повторы';

  @override
  String get liveEmpty => 'Сейчас нет прямых эфиров';

  @override
  String get replaysEmpty =>
      'Повторов пока нет — появятся после окончания эфира';

  @override
  String get liveBadge => 'LIVE';

  @override
  String watchingCount(String username, int count) {
    return '@$username · $count смотрят';
  }

  @override
  String replayDuration(String username, int mins) {
    return '@$username · $mins мин повтор';
  }

  @override
  String get goLiveTitle => 'Начать эфир';

  @override
  String get goLiveChromeHint =>
      'В Chrome MVP используется LIVE-заглушка (камера Agora пока нет).';

  @override
  String get streamTitleLabel => 'Название эфира';

  @override
  String get attachProducts => 'Прикрепить товары (необязательно)';

  @override
  String get noProductsYet => 'Товаров пока нет — эфир всё равно можно начать.';

  @override
  String get startLive => 'Начать эфир';

  @override
  String get enterTitle => 'Введите название';

  @override
  String get defaultLiveTitle => 'Прямая продажа';

  @override
  String get liveRoomTitle => 'Прямой эфир';

  @override
  String get livePlaceholderTitle => 'Заглушка эфира';

  @override
  String get livePlaceholderBody =>
      'A/V доступно на Android/iOS при STREAMING_PROVIDER=agora';

  @override
  String get liveAvNativeOnly =>
      'Прямой эфир с камерой — только на Android/iOS';

  @override
  String get liveConnecting => 'Подключение…';

  @override
  String get liveWaitingHost => 'Ожидание ведущего…';

  @override
  String get chatHint => 'Напишите что-нибудь…';

  @override
  String get endLive => 'Завершить';

  @override
  String get assistantTooltip => 'Ассистент';

  @override
  String get noProductsToPin => 'Нет товаров для закрепа';

  @override
  String get pinProductTitle => 'Закрепить товар';

  @override
  String get liveAssistantTitle => 'Ассистент эфира';

  @override
  String get liveAssistantBody =>
      'Подсказки по правилам (ADR-012). OpenAI — в Sprint 9+.';

  @override
  String get noSuggestions => 'Пока нет подсказок';

  @override
  String get pinAction => 'Закрепить';

  @override
  String get useAction => 'Применить';

  @override
  String get productPinned => 'Товар закреплён';

  @override
  String get productPinFailed => 'Не удалось закрепить товар';

  @override
  String get replyDrafted => 'Ответ добавлен в поле чата';

  @override
  String get replayTitle => 'Повтор';

  @override
  String get replayPlaceholder => 'Заглушка повтора';

  @override
  String get noReplayUrl => 'Нет URL повтора';

  @override
  String get messagesTitle => 'Сообщения';

  @override
  String get messageSeller => 'Написать продавцу';

  @override
  String get messageAboutOrder => 'Написать о заказе';

  @override
  String get noConversationsTitle => 'Нет сообщений';

  @override
  String get noConversationsSubtitle =>
      'Переписка с продавцами появится здесь.';

  @override
  String get typeMessageHint => 'Введите сообщение…';

  @override
  String get sendMessageFailed => 'Не удалось отправить сообщение';

  @override
  String get attachImage => 'Прикрепить изображение';

  @override
  String get imageUploadFailed => 'Не удалось загрузить изображение';

  @override
  String get blockUserTitle => 'Заблокировать';

  @override
  String get blockUserConfirm =>
      'Вы больше не сможете обмениваться сообщениями с этим пользователем.';

  @override
  String get blockUserAction => 'Заблокировать';

  @override
  String get unblockUserAction => 'Разблокировать';

  @override
  String get userBlocked => 'Пользователь заблокирован';

  @override
  String get userUnblocked => 'Пользователь разблокирован';

  @override
  String get blockUserFailed => 'Не удалось заблокировать';

  @override
  String get unblockUserFailed => 'Не удалось разблокировать';

  @override
  String get reportTitle => 'Пожаловаться';

  @override
  String get reportReasonSpam => 'Спам';

  @override
  String get reportReasonInappropriate => 'Неприемлемый контент';

  @override
  String get reportReasonScam => 'Мошенничество';

  @override
  String get reportReasonOther => 'Другое';

  @override
  String get reportSubmitted => 'Жалоба отправлена';

  @override
  String get reportAction => 'Пожаловаться';

  @override
  String get walletTitle => 'Кошелёк';

  @override
  String get walletBalance => 'Доступно';

  @override
  String get walletHeld => 'Заморожено';

  @override
  String get walletTotal => 'Всего';

  @override
  String get walletTopUp => 'Пополнить';

  @override
  String get walletWithdraw => 'Вывести';

  @override
  String get walletHistory => 'История операций';

  @override
  String get walletNoTransactions => 'Пока нет операций';

  @override
  String get walletNoTransactionsSubtitle =>
      'Пополните кошелёк, чтобы платить в один тап.';

  @override
  String get walletPendingWithdrawals => 'Выводы в обработке';

  @override
  String get walletTopUpTitle => 'Пополнение кошелька';

  @override
  String get walletWithdrawTitle => 'Вывод средств';

  @override
  String get walletAmountLabel => 'Сумма';

  @override
  String get walletAmountHint => '0';

  @override
  String get walletMethodLabel => 'Способ';

  @override
  String get walletCardNumberLabel => 'Номер карты';

  @override
  String get walletCardHolderLabel => 'Владелец карты';

  @override
  String get walletFeeLabel => 'Комиссия';

  @override
  String get walletTotalDebit => 'Спишется';

  @override
  String walletMinAmount(String amount) {
    return 'Минимум $amount';
  }

  @override
  String walletMaxAmount(String amount) {
    return 'Максимум $amount';
  }

  @override
  String get walletTopUpSuccess => 'Кошелёк пополнен';

  @override
  String get walletTopUpWaiting => 'Ждём подтверждения оплаты…';

  @override
  String get walletTopUpPending =>
      'Оплата ещё не подтверждена. Баланс обновится, как только придёт подтверждение.';

  @override
  String get walletTopUpFailed => 'Оплата не прошла';

  @override
  String get walletTopUpOpenFailed => 'Не удалось открыть страницу оплаты';

  @override
  String get walletWithdrawSuccess => 'Заявка на вывод создана';

  @override
  String get walletWithdrawCancelled => 'Заявка отменена';

  @override
  String get walletCancelWithdrawal => 'Отменить заявку';

  @override
  String get walletStatusRequested => 'В обработке';

  @override
  String get walletStatusProcessing => 'Отправляется';

  @override
  String get walletStatusCompleted => 'Выполнено';

  @override
  String get walletStatusRejected => 'Отклонено';

  @override
  String get walletStatusCancelled => 'Отменено';

  @override
  String get walletStatusPending => 'Ожидает';

  @override
  String get walletStatusFailed => 'Ошибка';

  @override
  String get walletTypeTopUp => 'Пополнение';

  @override
  String get walletTypeWithdrawal => 'Вывод';

  @override
  String get walletTypeOrderPayment => 'Оплата заказа';

  @override
  String get walletTypeRefund => 'Возврат';

  @override
  String get walletTypePayout => 'Выплата';

  @override
  String get walletTypeAdjustment => 'Корректировка';

  @override
  String walletCardMask(String last4) {
    return 'Карта •$last4';
  }

  @override
  String get walletInvalidAmount => 'Введите корректную сумму';

  @override
  String get walletInvalidCard => 'Введите корректный номер карты';

  @override
  String get walletMethodBitcoin => 'Bitcoin';

  @override
  String get walletMethodCard => 'Карта';

  @override
  String walletBitcoinApprox(String amount) {
    return '≈ $amount';
  }

  @override
  String walletChargeAmount(String amount) {
    return 'К списанию: $amount';
  }

  @override
  String walletChargeFromCard(String last4, String amount) {
    return 'С карты •••• $last4 будет списано $amount';
  }

  @override
  String get walletAddCard => 'Добавить карту';

  @override
  String get walletSaveCard => 'Сохранить';

  @override
  String get walletCancelAddCard => 'Отмена';

  @override
  String get walletCardExpMonth => 'Месяц';

  @override
  String get walletCardExpYear => 'Год';

  @override
  String get walletBitcoinPayTitle => 'Оплата Bitcoin';

  @override
  String get walletCopyAddress => 'Копировать адрес';

  @override
  String get walletAddressCopied => 'Адрес скопирован';

  @override
  String get walletBitcoinConfirmPaid => 'Я оплатил';

  @override
  String get walletBitcoinClose => 'Закрыть';

  @override
  String walletBitcoinExpires(String time) {
    return 'Действует до $time';
  }
}
