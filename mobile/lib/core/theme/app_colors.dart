import 'package:flutter/material.dart';

/// Raw colour tokens. Widgets should read colours from `Theme.of(context)`
/// or `context.palette` instead of referencing these directly, except for
/// surfaces that are intentionally theme-independent (video overlays).
abstract final class AppColors {
  // Brand
  static const Color brandPink = Color(0xFFFF3366);
  static const Color brandPinkDeep = Color(0xFFE01A4F);
  static const Color brandViolet = Color(0xFF7C3AED);
  static const Color brandVioletSoft = Color(0xFF9F67FF);

  // Semantic
  static const Color live = Color(0xFFFF2D55);
  static const Color success = Color(0xFF10B981);
  static const Color warning = Color(0xFFF59E0B);
  static const Color danger = Color(0xFFEF4444);
  static const Color info = Color(0xFF3B82F6);
  static const Color saved = Color(0xFFFFB020);

  // Dark scale — feed, live, immersive surfaces
  static const Color darkBackground = Color(0xFF0B0B0F);
  static const Color darkSurface = Color(0xFF14141A);
  static const Color darkSurfaceElevated = Color(0xFF1C1C24);
  static const Color darkSurfaceHigh = Color(0xFF24242E);
  static const Color darkOutline = Color(0xFF2E2E3A);
  static const Color darkTextPrimary = Color(0xFFFFFFFF);
  static const Color darkTextSecondary = Color(0xFFA1A1AA);
  static const Color darkTextTertiary = Color(0xFF71717A);

  // Light scale — catalogue, cart, orders, wallet
  static const Color lightBackground = Color(0xFFFAFAFA);
  static const Color lightSurface = Color(0xFFFFFFFF);
  static const Color lightSurfaceMuted = Color(0xFFF4F4F5);
  static const Color lightSurfaceHigh = Color(0xFFEDEDF0);
  static const Color lightOutline = Color(0xFFE4E4E7);
  static const Color lightTextPrimary = Color(0xFF0F172A);
  static const Color lightTextSecondary = Color(0xFF64748B);
  static const Color lightTextTertiary = Color(0xFF94A3B8);

  /// Always-white text used on top of video and brand gradients.
  static const Color onMedia = Color(0xFFFFFFFF);
  static const Color onMediaMuted = Color(0xB3FFFFFF);
}

/// Semantic colour set resolved for the active brightness.
///
/// Exposed via [AppPalette.of] / `context.palette` so screens never branch on
/// `Theme.of(context).brightness` by hand.
@immutable
class AppPalette extends ThemeExtension<AppPalette> {
  const AppPalette({
    required this.background,
    required this.surface,
    required this.surfaceElevated,
    required this.surfaceHigh,
    required this.outline,
    required this.textPrimary,
    required this.textSecondary,
    required this.textTertiary,
    required this.brand,
    required this.brandSecondary,
    required this.live,
    required this.success,
    required this.warning,
    required this.danger,
    required this.info,
    required this.saved,
    required this.shadow,
  });

  factory AppPalette.dark() => const AppPalette(
        background: AppColors.darkBackground,
        surface: AppColors.darkSurface,
        surfaceElevated: AppColors.darkSurfaceElevated,
        surfaceHigh: AppColors.darkSurfaceHigh,
        outline: AppColors.darkOutline,
        textPrimary: AppColors.darkTextPrimary,
        textSecondary: AppColors.darkTextSecondary,
        textTertiary: AppColors.darkTextTertiary,
        brand: AppColors.brandPink,
        brandSecondary: AppColors.brandVioletSoft,
        live: AppColors.live,
        success: AppColors.success,
        warning: AppColors.warning,
        danger: AppColors.danger,
        info: AppColors.info,
        saved: AppColors.saved,
        shadow: Color(0x66000000),
      );

  factory AppPalette.light() => const AppPalette(
        background: AppColors.lightBackground,
        surface: AppColors.lightSurface,
        surfaceElevated: AppColors.lightSurface,
        surfaceHigh: AppColors.lightSurfaceHigh,
        outline: AppColors.lightOutline,
        textPrimary: AppColors.lightTextPrimary,
        textSecondary: AppColors.lightTextSecondary,
        textTertiary: AppColors.lightTextTertiary,
        brand: AppColors.brandPink,
        brandSecondary: AppColors.brandViolet,
        live: AppColors.live,
        success: AppColors.success,
        warning: AppColors.warning,
        danger: AppColors.danger,
        info: AppColors.info,
        saved: AppColors.saved,
        shadow: Color(0x1A0F172A),
      );

  final Color background;
  final Color surface;
  final Color surfaceElevated;
  final Color surfaceHigh;
  final Color outline;
  final Color textPrimary;
  final Color textSecondary;
  final Color textTertiary;
  final Color brand;
  final Color brandSecondary;
  final Color live;
  final Color success;
  final Color warning;
  final Color danger;
  final Color info;
  final Color saved;
  final Color shadow;

  static AppPalette of(BuildContext context) =>
      Theme.of(context).extension<AppPalette>() ?? AppPalette.light();

  @override
  AppPalette copyWith({
    Color? background,
    Color? surface,
    Color? surfaceElevated,
    Color? surfaceHigh,
    Color? outline,
    Color? textPrimary,
    Color? textSecondary,
    Color? textTertiary,
    Color? brand,
    Color? brandSecondary,
    Color? live,
    Color? success,
    Color? warning,
    Color? danger,
    Color? info,
    Color? saved,
    Color? shadow,
  }) {
    return AppPalette(
      background: background ?? this.background,
      surface: surface ?? this.surface,
      surfaceElevated: surfaceElevated ?? this.surfaceElevated,
      surfaceHigh: surfaceHigh ?? this.surfaceHigh,
      outline: outline ?? this.outline,
      textPrimary: textPrimary ?? this.textPrimary,
      textSecondary: textSecondary ?? this.textSecondary,
      textTertiary: textTertiary ?? this.textTertiary,
      brand: brand ?? this.brand,
      brandSecondary: brandSecondary ?? this.brandSecondary,
      live: live ?? this.live,
      success: success ?? this.success,
      warning: warning ?? this.warning,
      danger: danger ?? this.danger,
      info: info ?? this.info,
      saved: saved ?? this.saved,
      shadow: shadow ?? this.shadow,
    );
  }

  @override
  AppPalette lerp(ThemeExtension<AppPalette>? other, double t) {
    if (other is! AppPalette) {
      return this;
    }
    Color mix(Color a, Color b) => Color.lerp(a, b, t)!;
    return AppPalette(
      background: mix(background, other.background),
      surface: mix(surface, other.surface),
      surfaceElevated: mix(surfaceElevated, other.surfaceElevated),
      surfaceHigh: mix(surfaceHigh, other.surfaceHigh),
      outline: mix(outline, other.outline),
      textPrimary: mix(textPrimary, other.textPrimary),
      textSecondary: mix(textSecondary, other.textSecondary),
      textTertiary: mix(textTertiary, other.textTertiary),
      brand: mix(brand, other.brand),
      brandSecondary: mix(brandSecondary, other.brandSecondary),
      live: mix(live, other.live),
      success: mix(success, other.success),
      warning: mix(warning, other.warning),
      danger: mix(danger, other.danger),
      info: mix(info, other.info),
      saved: mix(saved, other.saved),
      shadow: mix(shadow, other.shadow),
    );
  }
}
