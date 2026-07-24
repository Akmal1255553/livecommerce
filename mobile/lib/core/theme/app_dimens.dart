import 'package:flutter/material.dart';

import 'app_colors.dart';

/// 4pt spacing scale. Use these instead of ad-hoc numbers so rhythm stays
/// consistent across screens.
abstract final class AppSpacing {
  static const double xxs = 2;
  static const double xs = 4;
  static const double sm = 8;
  static const double md = 12;
  static const double lg = 16;
  static const double xl = 20;
  static const double xxl = 24;
  static const double xxxl = 32;
  static const double huge = 48;

  /// Standard horizontal page inset.
  static const EdgeInsets page = EdgeInsets.symmetric(horizontal: lg);
  static const EdgeInsets card = EdgeInsets.all(lg);
}

abstract final class AppRadius {
  static const double xs = 8;
  static const double sm = 12;
  static const double md = 16;
  static const double lg = 20;
  static const double xl = 28;
  static const double pill = 999;

  static const BorderRadius smAll = BorderRadius.all(Radius.circular(sm));
  static const BorderRadius mdAll = BorderRadius.all(Radius.circular(md));
  static const BorderRadius lgAll = BorderRadius.all(Radius.circular(lg));
  static const BorderRadius xlAll = BorderRadius.all(Radius.circular(xl));
  static const BorderRadius pillAll = BorderRadius.all(Radius.circular(pill));

  static const BorderRadius sheet =
      BorderRadius.vertical(top: Radius.circular(xl));
}

/// Motion durations. Anything above 400ms feels sluggish on a feed.
abstract final class AppDuration {
  static const Duration instant = Duration(milliseconds: 120);
  static const Duration fast = Duration(milliseconds: 180);
  static const Duration normal = Duration(milliseconds: 260);
  static const Duration slow = Duration(milliseconds: 400);

  static const Curve curve = Curves.easeOutCubic;
  static const Curve emphasized = Curves.easeOutBack;
}

abstract final class AppShadows {
  static List<BoxShadow> soft(Color shadow) => [
        BoxShadow(
          color: shadow,
          blurRadius: 16,
          offset: const Offset(0, 4),
        ),
      ];

  static List<BoxShadow> lifted(Color shadow) => [
        BoxShadow(
          color: shadow,
          blurRadius: 28,
          spreadRadius: -4,
          offset: const Offset(0, 12),
        ),
      ];

  /// Glow used behind primary gradient buttons.
  static List<BoxShadow> brandGlow({double opacity = 0.35}) => [
        BoxShadow(
          color: AppColors.brandPink.withValues(alpha: opacity),
          blurRadius: 24,
          spreadRadius: -6,
          offset: const Offset(0, 8),
        ),
      ];
}

abstract final class AppGradients {
  /// Primary brand gradient: pink to violet.
  static const LinearGradient brand = LinearGradient(
    begin: Alignment.centerLeft,
    end: Alignment.centerRight,
    colors: [AppColors.brandPink, AppColors.brandViolet],
  );

  static const LinearGradient brandDiagonal = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [AppColors.brandPink, AppColors.brandViolet],
  );

  static const LinearGradient live = LinearGradient(
    begin: Alignment.centerLeft,
    end: Alignment.centerRight,
    colors: [AppColors.live, AppColors.brandPink],
  );

  /// Scrim under captions so white text stays legible over any video frame.
  static const LinearGradient bottomScrim = LinearGradient(
    begin: Alignment.topCenter,
    end: Alignment.bottomCenter,
    colors: [
      Color(0x00000000),
      Color(0x66000000),
      Color(0xCC000000),
    ],
    stops: [0.0, 0.55, 1.0],
  );

  static const LinearGradient topScrim = LinearGradient(
    begin: Alignment.topCenter,
    end: Alignment.bottomCenter,
    colors: [Color(0x99000000), Color(0x00000000)],
  );
}
