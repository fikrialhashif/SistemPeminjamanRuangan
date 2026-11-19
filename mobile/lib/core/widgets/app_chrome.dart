import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../theme/app_theme.dart';

class AppLogoMark extends StatelessWidget {
  const AppLogoMark({this.size = 44, super.key});

  final double size;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: size,
      height: size,
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          Container(
            width: size,
            height: size,
            decoration: BoxDecoration(
              color: AppColors.primary,
              borderRadius: BorderRadius.circular(size * 0.24),
            ),
            child: Icon(
              Icons.business_center_rounded,
              color: Colors.white,
              size: size * 0.46,
            ),
          ),
          Positioned(
            top: -2,
            right: -2,
            child: Container(
              width: size * 0.24,
              height: size * 0.24,
              decoration: BoxDecoration(
                color: AppColors.accent,
                shape: BoxShape.circle,
                border: Border.all(color: AppColors.mint, width: 2),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class AppAvatar extends StatelessWidget {
  const AppAvatar({this.onTap, this.size = 38, super.key});

  final VoidCallback? onTap;
  final double size;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: onTap != null,
      label: 'Menu pengguna',
      child: InkResponse(
        onTap: onTap,
        radius: size * 0.75,
        child: SizedBox(
          width: size,
          height: size,
          child: Stack(
            clipBehavior: Clip.none,
            children: [
              Container(
                width: size,
                height: size,
                decoration: const BoxDecoration(
                  color: AppColors.primary,
                  shape: BoxShape.circle,
                ),
                child: Icon(
                  Icons.person_rounded,
                  size: size * 0.6,
                  color: Colors.white,
                ),
              ),
              Positioned(
                right: 0,
                bottom: 0,
                child: Container(
                  width: size * 0.3,
                  height: size * 0.3,
                  decoration: BoxDecoration(
                    color: const Color(0xFF42D392),
                    shape: BoxShape.circle,
                    border: Border.all(color: AppColors.mint, width: 2),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class AppBrandHeader extends StatelessWidget {
  const AppBrandHeader({
    required this.section,
    this.onNotificationsTap,
    this.onAvatarTap,
    super.key,
  });

  final String section;
  final VoidCallback? onNotificationsTap;
  final VoidCallback? onAvatarTap;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(18, 10, 12, 0),
      child: Row(
        children: [
          const AppLogoMark(),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'RESERVE & ASSET',
                  style: Theme.of(context).textTheme.labelSmall,
                ),
                const SizedBox(height: 2),
                Text(
                  section,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.titleMedium,
                ),
              ],
            ),
          ),
          IconButton(
            tooltip: 'Notifikasi',
            onPressed:
                onNotificationsTap ?? () => context.push('/notifications'),
            icon: const Icon(Icons.notifications_none_rounded),
          ),
          AppAvatar(onTap: onAvatarTap),
        ],
      ),
    );
  }
}

class AppDetailHeader extends StatelessWidget {
  const AppDetailHeader({
    required this.title,
    this.eyebrow = 'RESERVE & ASSET',
    this.onBack,
    this.onAvatarTap,
    super.key,
  });

  final String title;
  final String eyebrow;
  final VoidCallback? onBack;
  final VoidCallback? onAvatarTap;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(8, 8, 12, 4),
      child: Row(
        children: [
          IconButton(
            tooltip: 'Kembali',
            onPressed: onBack ?? () => context.pop(),
            icon: const Icon(Icons.arrow_back_rounded),
          ),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  eyebrow,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.labelSmall,
                ),
                const SizedBox(height: 2),
                Text(
                  title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.titleMedium,
                ),
              ],
            ),
          ),
          AppAvatar(onTap: onAvatarTap),
        ],
      ),
    );
  }
}

class AppBottomNav extends StatelessWidget {
  const AppBottomNav({
    required this.currentIndex,
    required this.showApprovals,
    super.key,
  });

  final int currentIndex;
  final bool showApprovals;

  @override
  Widget build(BuildContext context) {
    final routes = [
      '/resources',
      '/rooms',
      '/bookings',
      if (showApprovals) '/approvals',
    ];
    final items = [
      const BottomNavigationBarItem(
        icon: Icon(Icons.grid_view_outlined),
        activeIcon: Icon(Icons.grid_view_rounded),
        label: 'Katalog',
      ),
      const BottomNavigationBarItem(
        icon: Icon(Icons.meeting_room_outlined),
        activeIcon: Icon(Icons.meeting_room_rounded),
        label: 'Fitur Ruang',
      ),
      const BottomNavigationBarItem(
        icon: Icon(Icons.event_note_outlined),
        activeIcon: Icon(Icons.event_note_rounded),
        label: 'Booking Saya',
      ),
      if (showApprovals)
        const BottomNavigationBarItem(
          icon: Icon(Icons.fact_check_outlined),
          activeIcon: Icon(Icons.fact_check_rounded),
          label: 'Persetujuan',
        ),
    ];

    return DecoratedBox(
      decoration: const BoxDecoration(
        color: AppColors.surface,
        border: Border(top: BorderSide(color: AppColors.border)),
      ),
      child: SafeArea(
        top: false,
        child: BottomNavigationBar(
          currentIndex: currentIndex.clamp(0, items.length - 1),
          onTap: (index) {
            if (index == currentIndex) return;
            context.go(routes[index]);
          },
          items: items,
        ),
      ),
    );
  }
}
