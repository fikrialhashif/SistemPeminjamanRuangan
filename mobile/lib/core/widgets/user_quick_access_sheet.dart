import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../features/auth/presentation/auth_controller.dart';
import '../../features/face/data/face_providers.dart';
import '../session/user_role.dart';
import '../theme/app_theme.dart';
import 'app_components.dart';

Future<void> showUserQuickAccessSheet(BuildContext context) {
  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    builder: (_) => const UserQuickAccessSheet(),
  );
}

class UserQuickAccessSheet extends ConsumerWidget {
  const UserQuickAccessSheet({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(authControllerProvider).value?['user'];
    final userId = user is Map ? user['id'] : null;
    final enrolled = ref
        .watch(faceStatusProvider)
        .maybeWhen(
          data: (status) => status['enrolled'] == true,
          orElse: () => false,
        );

    return Container(
      decoration: const BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.vertical(top: Radius.circular(26)),
      ),
      padding: const EdgeInsets.fromLTRB(20, 10, 20, 18),
      child: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 44,
              height: 4,
              decoration: BoxDecoration(
                color: AppColors.border,
                borderRadius: BorderRadius.circular(4),
              ),
            ),
            const SizedBox(height: 18),
            Row(
              children: [
                const AppIconBox(
                  icon: Icons.bolt_rounded,
                  backgroundColor: AppColors.warningSoft,
                  foregroundColor: AppColors.warning,
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    'AKSES CEPAT PENGGUNA',
                    style: Theme.of(context).textTheme.labelSmall
                        ?.copyWith(color: AppColors.text, letterSpacing: 0.6),
                  ),
                ),
                AppPill(
                  label: enrolled ? 'Aktif Terverifikasi' : 'Belum verifikasi',
                  icon: enrolled
                      ? Icons.verified_rounded
                      : Icons.verified_outlined,
                  backgroundColor: enrolled
                      ? AppColors.mintStrong
                      : AppColors.mintSoft,
                ),
              ],
            ),
            const SizedBox(height: 12),
            _QuickItem(
              icon: Icons.person_outline_rounded,
              title: 'Profil saya',
              subtitle: 'Ubah nama & password',
              onTap: () => _open(context, '/profile'),
            ),
            if (UserRole.shouldShowApprovals(user))
              _QuickItem(
                icon: Icons.fact_check_outlined,
                title: 'Persetujuan',
                subtitle: 'Tinjau pengajuan booking',
                onTap: () => _open(context, '/approvals'),
              ),
            _QuickItem(
              icon: Icons.face_retouching_natural,
              title: 'Daftar wajah',
              subtitle: 'Kelola biometrik verifikasi',
              onTap: () => _open(context, '/face/enrollment'),
            ),
            _QuickItem(
              icon: Icons.notifications_none_rounded,
              title: 'Notifikasi',
              subtitle: 'Lihat pembaruan terbaru',
              onTap: () => _open(context, '/notifications'),
            ),
            _QuickItem(
              icon: Icons.warning_amber_rounded,
              title: 'Status penalti',
              subtitle: 'Cek riwayat pelanggaran',
              onTap: () => _open(context, '/penalties'),
            ),
            if (UserRole.shouldShowLoans(user))
              _QuickItem(
                icon: Icons.inventory_2_outlined,
                title: 'Peminjaman aset',
                subtitle: 'Serah-terima dan pengembalian aset',
                onTap: () => _open(context, '/manager/loans'),
              ),
            const Divider(height: 22),
            _QuickItem(
              icon: Icons.logout_rounded,
              title: 'Keluar',
              subtitle: 'Ganti akun operasional',
              foregroundColor: AppColors.danger,
              onTap: () => _logout(context, ref),
            ),
            const SizedBox(height: 10),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
              decoration: BoxDecoration(
                color: AppColors.mintSoft,
                borderRadius: BorderRadius.circular(12),
              ),
              child: Text(
                'ID Pengguna: ${userId == null ? 'RES-••••' : 'RES-${userId.toString().padLeft(4, '0')}'} • SSO Aktif',
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _open(BuildContext context, String path) {
    final router = GoRouter.of(context);
    Navigator.of(context).pop();
    router.push(path);
  }

  Future<void> _logout(BuildContext context, WidgetRef ref) async {
    final auth = ref.read(authControllerProvider.notifier);
    final router = GoRouter.of(context);
    Navigator.of(context).pop();
    await auth.logout();
    router.go('/');
  }
}

class _QuickItem extends StatelessWidget {
  const _QuickItem({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
    this.foregroundColor = AppColors.primary,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;
  final Color foregroundColor;

  @override
  Widget build(BuildContext context) {
    return ListTile(
      contentPadding: const EdgeInsets.symmetric(horizontal: 0, vertical: 2),
      onTap: onTap,
      leading: AppIconBox(
        icon: icon,
        size: 40,
        backgroundColor: foregroundColor == AppColors.danger
            ? AppColors.dangerSoft
            : AppColors.mintSoft,
        foregroundColor: foregroundColor,
      ),
      title: Text(
        title,
        style: TextStyle(
          color: foregroundColor,
          fontWeight: FontWeight.w700,
          fontSize: 14,
        ),
      ),
      subtitle: Text(subtitle),
      trailing: const Icon(Icons.chevron_right_rounded, size: 20),
    );
  }
}
