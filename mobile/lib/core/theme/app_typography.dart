import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

/// Type scale for the app.
///
/// Rubik carries headings and numerics (prices, counters); Nunito Sans carries
/// body copy. Both ship Cyrillic and Latin glyphs, which the ru/uz locales need.
abstract final class AppTypography {
  static TextTheme textTheme(Color primary, Color secondary) {
    final display = GoogleFonts.rubikTextTheme();
    final body = GoogleFonts.nunitoSansTextTheme();

    return TextTheme(
      displayLarge: display.displayLarge?.copyWith(
        fontSize: 40,
        height: 1.1,
        fontWeight: FontWeight.w800,
        letterSpacing: -1.0,
        color: primary,
      ),
      displayMedium: display.displayMedium?.copyWith(
        fontSize: 34,
        height: 1.12,
        fontWeight: FontWeight.w800,
        letterSpacing: -0.8,
        color: primary,
      ),
      displaySmall: display.displaySmall?.copyWith(
        fontSize: 28,
        height: 1.15,
        fontWeight: FontWeight.w700,
        letterSpacing: -0.6,
        color: primary,
      ),
      headlineMedium: display.headlineMedium?.copyWith(
        fontSize: 24,
        height: 1.2,
        fontWeight: FontWeight.w700,
        letterSpacing: -0.4,
        color: primary,
      ),
      headlineSmall: display.headlineSmall?.copyWith(
        fontSize: 20,
        height: 1.25,
        fontWeight: FontWeight.w700,
        letterSpacing: -0.3,
        color: primary,
      ),
      titleLarge: display.titleLarge?.copyWith(
        fontSize: 18,
        height: 1.3,
        fontWeight: FontWeight.w700,
        letterSpacing: -0.2,
        color: primary,
      ),
      titleMedium: display.titleMedium?.copyWith(
        fontSize: 16,
        height: 1.35,
        fontWeight: FontWeight.w600,
        color: primary,
      ),
      titleSmall: display.titleSmall?.copyWith(
        fontSize: 14,
        height: 1.4,
        fontWeight: FontWeight.w600,
        color: primary,
      ),
      bodyLarge: body.bodyLarge?.copyWith(
        fontSize: 16,
        height: 1.5,
        fontWeight: FontWeight.w400,
        color: primary,
      ),
      bodyMedium: body.bodyMedium?.copyWith(
        fontSize: 14,
        height: 1.5,
        fontWeight: FontWeight.w400,
        color: primary,
      ),
      bodySmall: body.bodySmall?.copyWith(
        fontSize: 13,
        height: 1.45,
        fontWeight: FontWeight.w400,
        color: secondary,
      ),
      labelLarge: display.labelLarge?.copyWith(
        fontSize: 15,
        height: 1.2,
        fontWeight: FontWeight.w600,
        letterSpacing: 0.1,
        color: primary,
      ),
      labelMedium: display.labelMedium?.copyWith(
        fontSize: 13,
        height: 1.2,
        fontWeight: FontWeight.w600,
        color: secondary,
      ),
      labelSmall: display.labelSmall?.copyWith(
        fontSize: 11,
        height: 1.2,
        fontWeight: FontWeight.w600,
        letterSpacing: 0.4,
        color: secondary,
      ),
    );
  }

  /// Tabular figures keep prices and counters from jittering as they update.
  static TextStyle numeric({
    required double fontSize,
    FontWeight fontWeight = FontWeight.w700,
    Color? color,
    double letterSpacing = -0.3,
  }) {
    return GoogleFonts.rubik(
      fontSize: fontSize,
      fontWeight: fontWeight,
      color: color,
      letterSpacing: letterSpacing,
      fontFeatures: const [FontFeature.tabularFigures()],
    );
  }

  /// Overlay text sits on video, so it always needs a shadow to stay readable.
  static List<Shadow> get mediaShadow => const [
        Shadow(color: Color(0x99000000), blurRadius: 8, offset: Offset(0, 1)),
      ];
}
