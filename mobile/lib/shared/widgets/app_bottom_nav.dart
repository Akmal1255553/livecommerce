import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:livecommerce_mobile/core/theme/app_colors.dart';
import 'package:livecommerce_mobile/core/theme/app_dimens.dart';

enum AppTab { home, discover, create, inbox, profile }

/// Floating bottom navigation shared by the top-level destinations.
///
/// On immersive surfaces (feed, live) pass [transparent] so the bar blends into
/// the video instead of cutting it with a solid slab.
class AppBottomNav extends StatelessWidget {
  const AppBottomNav({
    super.key,
    required this.current,
    this.transparent = false,
  });

  final AppTab current;
  final bool transparent;

  static const double barHeight = 62;

  /// Space a scrollable body should reserve so content clears the bar.
  static double reservedSpace(BuildContext context) =>
      barHeight + MediaQuery.paddingOf(context).bottom + AppSpacing.md;

  void _go(BuildContext context, AppTab tab) {
    if (tab == current) {
      return;
    }
    switch (tab) {
      case AppTab.home:
        context.go('/home');
      case AppTab.discover:
        context.push('/live');
      case AppTab.create:
        context.push('/live/go');
      case AppTab.inbox:
        context.push('/conversations');
      case AppTab.profile:
        context.push('/profile');
    }
  }

  @override
  Widget build(BuildContext context) {
    final palette = AppPalette.of(context);

    final bar = Container(
      height: barHeight,
      decoration: BoxDecoration(
        color: transparent
            ? Colors.black.withValues(alpha: 0.55)
            : palette.surface,
        borderRadius: AppRadius.xlAll,
        border: Border.all(
          color: transparent
              ? Colors.white.withValues(alpha: 0.10)
              : palette.outline,
        ),
        boxShadow: transparent ? null : AppShadows.lifted(palette.shadow),
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceEvenly,
        children: [
          _NavIcon(
            icon: Icons.home_rounded,
            selected: current == AppTab.home,
            transparent: transparent,
            onTap: () => _go(context, AppTab.home),
          ),
          _NavIcon(
            icon: Icons.explore_outlined,
            selected: current == AppTab.discover,
            transparent: transparent,
            onTap: () => _go(context, AppTab.discover),
          ),
          _CreateButton(onTap: () => _go(context, AppTab.create)),
          _NavIcon(
            icon: Icons.forum_outlined,
            selected: current == AppTab.inbox,
            transparent: transparent,
            onTap: () => _go(context, AppTab.inbox),
          ),
          _NavIcon(
            icon: Icons.person_outline_rounded,
            selected: current == AppTab.profile,
            transparent: transparent,
            onTap: () => _go(context, AppTab.profile),
          ),
        ],
      ),
    );

    return SafeArea(
      top: false,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(
          AppSpacing.lg,
          0,
          AppSpacing.lg,
          AppSpacing.md,
        ),
        child: transparent
            ? ClipRRect(borderRadius: AppRadius.xlAll, child: bar)
            : bar,
      ),
    );
  }
}

class _NavIcon extends StatelessWidget {
  const _NavIcon({
    required this.icon,
    required this.selected,
    required this.transparent,
    required this.onTap,
  });

  final IconData icon;
  final bool selected;
  final bool transparent;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final palette = AppPalette.of(context);
    final inactive =
        transparent ? Colors.white.withValues(alpha: 0.6) : palette.textTertiary;
    final active = transparent ? Colors.white : palette.brand;

    return InkResponse(
      onTap: onTap,
      radius: 28,
      child: SizedBox(
        width: 56,
        height: AppBottomNav.barHeight,
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, size: 26, color: selected ? active : inactive),
            const SizedBox(height: AppSpacing.xs + 1),
            AnimatedContainer(
              duration: AppDuration.fast,
              height: 4,
              width: selected ? 4 : 0,
              decoration: BoxDecoration(
                color: active,
                shape: BoxShape.circle,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _CreateButton extends StatelessWidget {
  const _CreateButton({required this.onTap});

  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: 50,
        height: 38,
        decoration: BoxDecoration(
          gradient: AppGradients.brand,
          borderRadius: AppRadius.smAll,
          boxShadow: AppShadows.brandGlow(opacity: 0.5),
        ),
        child: const Icon(Icons.add_rounded, color: Colors.white, size: 26),
      ),
    );
  }
}
