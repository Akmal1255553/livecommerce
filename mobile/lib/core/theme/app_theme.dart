import 'package:flutter/cupertino.dart' show CupertinoPageTransitionsBuilder;
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import 'app_colors.dart';
import 'app_dimens.dart';
import 'app_typography.dart';

/// Application themes.
///
/// The light theme is the default shell (catalogue, cart, orders, wallet).
/// The dark theme is used both as the user-selectable dark mode and,
/// unconditionally, for immersive video surfaces via [AppTheme.immersive].
abstract final class AppTheme {
  static ThemeData get light => _build(AppPalette.light(), Brightness.light);

  static ThemeData get dark => _build(AppPalette.dark(), Brightness.dark);

  /// Forces the dark palette regardless of the user's theme mode. Wrap feed,
  /// live and other video-first screens with this so overlays keep contrast.
  static Widget immersive({required Widget child}) {
    return Theme(data: dark, child: child);
  }

  /// Status bar / navigation bar styling for immersive dark surfaces.
  static const SystemUiOverlayStyle immersiveOverlay = SystemUiOverlayStyle(
    statusBarColor: Colors.transparent,
    statusBarIconBrightness: Brightness.light,
    statusBarBrightness: Brightness.dark,
    systemNavigationBarColor: AppColors.darkBackground,
    systemNavigationBarIconBrightness: Brightness.light,
  );

  static ThemeData _build(AppPalette palette, Brightness brightness) {
    final isDark = brightness == Brightness.dark;

    final scheme = ColorScheme(
      brightness: brightness,
      primary: palette.brand,
      onPrimary: Colors.white,
      primaryContainer: isDark
          ? AppColors.brandPink.withValues(alpha: 0.18)
          : AppColors.brandPink.withValues(alpha: 0.10),
      onPrimaryContainer: isDark ? Colors.white : AppColors.brandPinkDeep,
      secondary: palette.brandSecondary,
      onSecondary: Colors.white,
      secondaryContainer: palette.brandSecondary.withValues(alpha: 0.14),
      onSecondaryContainer: palette.textPrimary,
      error: palette.danger,
      onError: Colors.white,
      errorContainer: palette.danger.withValues(alpha: 0.12),
      onErrorContainer: palette.danger,
      surface: palette.surface,
      onSurface: palette.textPrimary,
      onSurfaceVariant: palette.textSecondary,
      surfaceContainerLowest: palette.background,
      surfaceContainerLow: palette.surface,
      surfaceContainer: palette.surfaceElevated,
      surfaceContainerHigh: palette.surfaceHigh,
      surfaceContainerHighest: palette.surfaceHigh,
      outline: palette.outline,
      outlineVariant: palette.outline,
      shadow: palette.shadow,
      scrim: const Color(0x99000000),
      inverseSurface: isDark ? AppColors.lightSurface : AppColors.darkSurface,
      onInverseSurface:
          isDark ? AppColors.lightTextPrimary : AppColors.darkTextPrimary,
      inversePrimary: palette.brandSecondary,
    );

    final textTheme =
        AppTypography.textTheme(palette.textPrimary, palette.textSecondary);

    return ThemeData(
      useMaterial3: true,
      brightness: brightness,
      colorScheme: scheme,
      scaffoldBackgroundColor: palette.background,
      canvasColor: palette.background,
      textTheme: textTheme,
      splashFactory: InkSparkle.splashFactory,
      extensions: [palette],
      appBarTheme: AppBarTheme(
        centerTitle: false,
        elevation: 0,
        scrolledUnderElevation: 0,
        backgroundColor: palette.background,
        surfaceTintColor: Colors.transparent,
        foregroundColor: palette.textPrimary,
        titleTextStyle: textTheme.titleLarge,
        systemOverlayStyle: isDark
            ? SystemUiOverlayStyle.light
            : SystemUiOverlayStyle.dark,
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          minimumSize: const Size.fromHeight(52),
          backgroundColor: palette.brand,
          foregroundColor: Colors.white,
          disabledBackgroundColor: palette.surfaceHigh,
          disabledForegroundColor: palette.textTertiary,
          elevation: 0,
          shape: const RoundedRectangleBorder(borderRadius: AppRadius.mdAll),
          textStyle: textTheme.labelLarge?.copyWith(fontWeight: FontWeight.w700),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          minimumSize: const Size.fromHeight(52),
          foregroundColor: palette.textPrimary,
          side: BorderSide(color: palette.outline),
          shape: const RoundedRectangleBorder(borderRadius: AppRadius.mdAll),
          textStyle: textTheme.labelLarge,
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: palette.brand,
          minimumSize: const Size(48, 44),
          shape: const RoundedRectangleBorder(borderRadius: AppRadius.smAll),
          textStyle: textTheme.labelLarge,
        ),
      ),
      iconButtonTheme: IconButtonThemeData(
        style: IconButton.styleFrom(
          foregroundColor: palette.textPrimary,
          minimumSize: const Size(44, 44),
        ),
      ),
      iconTheme: IconThemeData(color: palette.textPrimary, size: 24),
      cardTheme: CardThemeData(
        color: palette.surfaceElevated,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: AppRadius.lgAll,
          side: BorderSide(color: palette.outline, width: isDark ? 1 : 0.8),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor:
            isDark ? palette.surfaceElevated : AppColors.lightSurfaceMuted,
        hintStyle: textTheme.bodyMedium?.copyWith(color: palette.textTertiary),
        labelStyle: textTheme.bodyMedium?.copyWith(color: palette.textSecondary),
        floatingLabelStyle: textTheme.labelMedium?.copyWith(color: palette.brand),
        prefixIconColor: palette.textSecondary,
        suffixIconColor: palette.textSecondary,
        contentPadding: const EdgeInsets.symmetric(
          horizontal: AppSpacing.lg,
          vertical: AppSpacing.lg,
        ),
        border: const OutlineInputBorder(
          borderRadius: AppRadius.mdAll,
          borderSide: BorderSide.none,
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: AppRadius.mdAll,
          borderSide: BorderSide(color: palette.outline),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: AppRadius.mdAll,
          borderSide: BorderSide(color: palette.brand, width: 1.6),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: AppRadius.mdAll,
          borderSide: BorderSide(color: palette.danger),
        ),
        focusedErrorBorder: OutlineInputBorder(
          borderRadius: AppRadius.mdAll,
          borderSide: BorderSide(color: palette.danger, width: 1.6),
        ),
      ),
      chipTheme: ChipThemeData(
        backgroundColor: palette.surfaceHigh,
        selectedColor: palette.brand.withValues(alpha: 0.14),
        side: BorderSide(color: palette.outline),
        labelStyle: textTheme.labelMedium?.copyWith(color: palette.textPrimary),
        padding: const EdgeInsets.symmetric(
          horizontal: AppSpacing.md,
          vertical: AppSpacing.sm,
        ),
        shape: const RoundedRectangleBorder(borderRadius: AppRadius.pillAll),
      ),
      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        backgroundColor: isDark ? palette.surfaceHigh : AppColors.darkSurface,
        contentTextStyle: textTheme.bodyMedium?.copyWith(color: Colors.white),
        actionTextColor: AppColors.brandVioletSoft,
        insetPadding: const EdgeInsets.all(AppSpacing.lg),
        shape: const RoundedRectangleBorder(borderRadius: AppRadius.smAll),
      ),
      dividerTheme: DividerThemeData(
        color: palette.outline,
        space: 1,
        thickness: 1,
      ),
      bottomSheetTheme: BottomSheetThemeData(
        backgroundColor: palette.background,
        surfaceTintColor: Colors.transparent,
        modalBackgroundColor: palette.background,
        showDragHandle: true,
        dragHandleColor: palette.outline,
        dragHandleSize: const Size(40, 4),
        shape: const RoundedRectangleBorder(borderRadius: AppRadius.sheet),
      ),
      dialogTheme: DialogThemeData(
        backgroundColor: palette.surfaceElevated,
        surfaceTintColor: Colors.transparent,
        shape: const RoundedRectangleBorder(borderRadius: AppRadius.lgAll),
        titleTextStyle: textTheme.titleLarge,
        contentTextStyle: textTheme.bodyMedium,
      ),
      listTileTheme: ListTileThemeData(
        iconColor: palette.textSecondary,
        titleTextStyle: textTheme.titleSmall,
        subtitleTextStyle: textTheme.bodySmall,
        shape: const RoundedRectangleBorder(borderRadius: AppRadius.smAll),
        contentPadding: const EdgeInsets.symmetric(
          horizontal: AppSpacing.lg,
          vertical: AppSpacing.xs,
        ),
      ),
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: palette.surface,
        surfaceTintColor: Colors.transparent,
        indicatorColor: palette.brand.withValues(alpha: 0.14),
        elevation: 0,
        height: 64,
        labelTextStyle: WidgetStateProperty.resolveWith(
          (states) => textTheme.labelSmall?.copyWith(
            color: states.contains(WidgetState.selected)
                ? palette.brand
                : palette.textTertiary,
          ),
        ),
      ),
      tabBarTheme: TabBarThemeData(
        labelColor: palette.textPrimary,
        unselectedLabelColor: palette.textTertiary,
        labelStyle: textTheme.labelLarge,
        unselectedLabelStyle: textTheme.labelLarge,
        indicatorSize: TabBarIndicatorSize.label,
        dividerColor: Colors.transparent,
        indicator: UnderlineTabIndicator(
          borderSide: BorderSide(color: palette.brand, width: 3),
          borderRadius: BorderRadius.circular(3),
        ),
      ),
      progressIndicatorTheme: ProgressIndicatorThemeData(
        color: palette.brand,
        linearTrackColor: palette.surfaceHigh,
        circularTrackColor: Colors.transparent,
      ),
      tooltipTheme: TooltipThemeData(
        decoration: BoxDecoration(
          color: isDark ? palette.surfaceHigh : AppColors.darkSurface,
          borderRadius: AppRadius.smAll,
        ),
        textStyle: textTheme.bodySmall?.copyWith(color: Colors.white),
      ),
      badgeTheme: BadgeThemeData(
        backgroundColor: palette.brand,
        textColor: Colors.white,
        textStyle: textTheme.labelSmall?.copyWith(color: Colors.white),
      ),
      switchTheme: SwitchThemeData(
        thumbColor: WidgetStateProperty.resolveWith(
          (states) => states.contains(WidgetState.selected)
              ? Colors.white
              : palette.textTertiary,
        ),
        trackColor: WidgetStateProperty.resolveWith(
          (states) => states.contains(WidgetState.selected)
              ? palette.brand
              : palette.surfaceHigh,
        ),
        trackOutlineColor: WidgetStateProperty.all(Colors.transparent),
      ),
      pageTransitionsTheme: const PageTransitionsTheme(
        builders: {
          TargetPlatform.android: CupertinoPageTransitionsBuilder(),
          TargetPlatform.iOS: CupertinoPageTransitionsBuilder(),
        },
      ),
    );
  }
}

/// Shorthand accessors so widgets can write `context.palette.brand`.
extension AppThemeContext on BuildContext {
  AppPalette get palette => AppPalette.of(this);
  TextTheme get texts => Theme.of(this).textTheme;
  ColorScheme get colors => Theme.of(this).colorScheme;
  bool get isDarkTheme => Theme.of(this).brightness == Brightness.dark;
}
