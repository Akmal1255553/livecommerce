import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';

/// Disk-cached network image used across feed, commerce, and profile surfaces.
class AppCachedImage extends StatelessWidget {
  const AppCachedImage({
    super.key,
    required this.url,
    this.fit = BoxFit.cover,
    this.width,
    this.height,
    this.memCacheWidth,
    this.placeholderColor = const Color(0xFF1A1A1A),
    this.errorWidget,
    this.borderRadius,
  });

  final String url;
  final BoxFit fit;
  final double? width;
  final double? height;
  final int? memCacheWidth;
  final Color placeholderColor;
  final Widget? errorWidget;
  final BorderRadius? borderRadius;

  @override
  Widget build(BuildContext context) {
    final image = CachedNetworkImage(
      imageUrl: url,
      fit: fit,
      width: width,
      height: height,
      memCacheWidth: memCacheWidth,
      fadeInDuration: const Duration(milliseconds: 180),
      placeholder: (_, __) => ColoredBox(color: placeholderColor),
      errorWidget: (_, __, ___) =>
          errorWidget ?? ColoredBox(color: placeholderColor),
    );

    if (borderRadius == null) {
      return image;
    }

    return ClipRRect(
      borderRadius: borderRadius!,
      child: image,
    );
  }
}

/// Circular avatar backed by [AppCachedImage].
class AppCachedAvatar extends StatelessWidget {
  const AppCachedAvatar({
    super.key,
    this.url,
    this.radius = 20,
    this.backgroundColor = const Color(0xFF333333),
  });

  final String? url;
  final double radius;
  final Color backgroundColor;

  @override
  Widget build(BuildContext context) {
    if (url == null || url!.isEmpty) {
      return CircleAvatar(
        radius: radius,
        backgroundColor: backgroundColor,
        child: const Icon(Icons.person, color: Colors.white70),
      );
    }

    return CircleAvatar(
      radius: radius,
      backgroundColor: backgroundColor,
      backgroundImage: CachedNetworkImageProvider(url!),
      onBackgroundImageError: (_, __) {},
    );
  }
}
