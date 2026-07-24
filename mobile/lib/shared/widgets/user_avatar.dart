import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:livecommerce_mobile/core/theme/app_colors.dart';
import 'package:livecommerce_mobile/core/theme/app_dimens.dart';

/// Avatar with an initials fallback.
///
/// Initials are tinted deterministically from the username so a list of
/// avatarless users still reads as distinct people.
class UserAvatar extends StatelessWidget {
  const UserAvatar({
    super.key,
    required this.username,
    this.avatarUrl,
    this.radius = 20,
    this.ring = false,
  });

  final String username;
  final String? avatarUrl;
  final double radius;

  /// Gradient ring, used for live/story-like emphasis.
  final bool ring;

  static const List<List<Color>> _palettes = [
    [AppColors.brandPink, AppColors.brandViolet],
    [Color(0xFF00C2FF), Color(0xFF6C5CE7)],
    [Color(0xFFFFA62B), Color(0xFFFF3366)],
    [Color(0xFF12B76A), Color(0xFF00C2FF)],
    [Color(0xFF7C3AED), Color(0xFFFF66A1)],
  ];

  String get _initials {
    final cleaned = username.trim();
    if (cleaned.isEmpty) {
      return '?';
    }
    final parts = cleaned.split(RegExp(r'[\s._-]+'))
      ..removeWhere((part) => part.isEmpty);
    if (parts.length >= 2) {
      return (parts[0][0] + parts[1][0]).toUpperCase();
    }
    return cleaned.substring(0, cleaned.length >= 2 ? 2 : 1).toUpperCase();
  }

  List<Color> get _palette =>
      _palettes[username.hashCode.abs() % _palettes.length];

  @override
  Widget build(BuildContext context) {
    final size = radius * 2;
    final url = avatarUrl;

    Widget avatar = SizedBox(
      width: size,
      height: size,
      child: ClipOval(
        child: url != null && url.isNotEmpty
            ? CachedNetworkImage(
                imageUrl: url,
                fit: BoxFit.cover,
                memCacheWidth: (size * 3).round(),
                placeholder: (_, __) => _initialsBox(),
                errorWidget: (_, __, ___) => _initialsBox(),
              )
            : _initialsBox(),
      ),
    );

    if (ring) {
      avatar = Container(
        padding: const EdgeInsets.all(2),
        decoration: const BoxDecoration(
          gradient: AppGradients.brandDiagonal,
          shape: BoxShape.circle,
        ),
        child: Container(
          padding: const EdgeInsets.all(2),
          decoration: BoxDecoration(
            color: Theme.of(context).colorScheme.surface,
            shape: BoxShape.circle,
          ),
          child: avatar,
        ),
      );
    }

    return avatar;
  }

  Widget _initialsBox() {
    final colors = _palette;
    return DecoratedBox(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: colors,
        ),
      ),
      child: Center(
        child: Text(
          _initials,
          style: TextStyle(
            color: Colors.white,
            fontWeight: FontWeight.w700,
            fontSize: radius * 0.72,
            letterSpacing: -0.2,
          ),
        ),
      ),
    );
  }
}
