import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/formatting/formatters.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/widgets/app_chrome.dart';
import '../../../core/widgets/app_components.dart';
import '../../../core/widgets/status_views.dart';
import '../../../core/widgets/user_quick_access_sheet.dart';
import '../../check_in/data/check_in_repository.dart';
import 'booking_providers.dart';

class BookingDetailPage extends ConsumerWidget {
  const BookingDetailPage({
    required this.bookingId,
    this.justCheckedIn = false,
    super.key,
  });

  final String bookingId;
  final bool justCheckedIn;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final detail = ref.watch(bookingDetailProvider(bookingId));

    return Scaffold(
      body: SafeArea(
        bottom: false,
        child: Column(
          children: [
            AppDetailHeader(
              eyebrow: '05 Detail Booking & Bukti Check-in',
              title: 'Detail booking',
              onBack: () {
                if (context.canPop()) {
                  context.pop();
                } else {
                  context.go('/bookings');
                }
              },
              onAvatarTap: () => showUserQuickAccessSheet(context),
            ),
            Expanded(
              child: detail.when(
                loading: () => const Center(
                  child: CircularProgressIndicator(
                    semanticsLabel: 'Memuat detail booking',
                  ),
                ),
                error: (error, _) => ErrorView(
                  error: error,
                  onRetry: () =>
                      ref.invalidate(bookingDetailProvider(bookingId)),
                ),
                data: (booking) => _BookingDetailBody(
                  booking: booking,
                  justCheckedIn: justCheckedIn,
                  onRefresh: () =>
                      ref.invalidate(bookingDetailProvider(bookingId)),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _BookingDetailBody extends StatelessWidget {
  const _BookingDetailBody({
    required this.booking,
    required this.justCheckedIn,
    required this.onRefresh,
  });

  final Map<String, dynamic> booking;
  final bool justCheckedIn;
  final VoidCallback onRefresh;

  @override
  Widget build(BuildContext context) {
    final colorScheme = Theme.of(context).colorScheme;
    final resource = booking['resource'] as Map?;
    final status = booking['status']?.toString();
    final location = resource?['location'];
    final locationName = location is Map
        ? location['name']?.toString() ?? '-'
        : location?.toString() ?? '-';
    final attempts =
        (booking['check_in_attempts'] as List?) ??
        (booking['checkInAttempts'] as List?) ??
        const [];
    final latest = attempts.isEmpty
        ? null
        : Map<String, dynamic>.from(attempts.last as Map);
    final accepted = status == 'checked_in' || latest?['result'] == 'accepted';

    return ListView(
      padding: const EdgeInsets.fromLTRB(18, 8, 18, 24),
      children: [
        _ReceiptMetaBar(onRefresh: onRefresh),
        const SizedBox(height: 12),
        if (justCheckedIn || accepted) ...[
          _successBanner(colorScheme),
          const SizedBox(height: 14),
        ],
        if (status == 'pending') ...[
          _infoBanner(
            context,
            colorScheme,
            icon: Icons.schedule,
            title: 'Menunggu persetujuan approver',
            description: 'Check-in tersedia setelah booking ini disetujui.',
          ),
          const SizedBox(height: 14),
        ],
        _section(
          context,
          title: 'Informasi booking',
          icon: Icons.receipt_long_outlined,
          children: [
            _InfoRow(
              label: 'Judul',
              value:
                  booking['title']?.toString() ??
                  resource?['name']?.toString() ??
                  'Booking',
            ),
            _InfoRow(label: 'Status', value: statusLabel(status)),
            _InfoRow(
              label: 'Ruangan',
              value: resource?['name']?.toString() ?? '-',
            ),
            _InfoRow(label: 'Lokasi', value: locationName),
            _InfoRow(
              label: 'Mulai',
              value: formatWibDateTime(booking['starts_at']),
            ),
            _InfoRow(
              label: 'Selesai',
              value: formatWibDateTime(booking['ends_at']),
            ),
            if (booking['notes'] != null)
              _InfoRow(label: 'Catatan', value: booking['notes'].toString()),
          ],
        ),
        const SizedBox(height: 14),
        _section(
          context,
          title: 'Bukti check-in',
          icon: Icons.fact_check_outlined,
          children: [
            if (latest == null)
              const Padding(
                padding: EdgeInsets.symmetric(vertical: 8),
                child: Text('Belum ada check-in untuk booking ini.'),
              )
            else ...[
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 12,
                  vertical: 10,
                ),
                decoration: BoxDecoration(
                  color: AppColors.mintSoft,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Row(
                  children: [
                    const Icon(
                      Icons.schedule_rounded,
                      size: 16,
                      color: AppColors.primary,
                    ),
                    const SizedBox(width: 6),
                    Expanded(
                      child: Text(
                        'Tercatat: ${formatWibDateTime(latest['created_at'])}',
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(
                          color: AppColors.text,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 8),
              _ProofRow(label: 'QR ruangan', valid: latest['qr_valid'] == true),
              _ProofRow(
                label: 'Lokasi',
                valid: latest['gps_valid'] == true,
                detail: latest['gps_accuracy'] == null
                    ? null
                    : 'Akurasi ${latest['gps_accuracy']} m',
              ),
              _ProofRow(
                label: 'Wajah',
                valid: latest['face_valid'] == true,
                detail: latest['face_score'] == null
                    ? null
                    : 'Skor ${latest['face_score']}',
              ),
              if (latest['rejection_reasons'] != null)
                Padding(
                  padding: const EdgeInsets.only(top: 8),
                  child: Text(
                    checkInDecisionMessage({
                      'rejection_reasons': latest['rejection_reasons'],
                    }),
                    style: TextStyle(color: colorScheme.error),
                  ),
                ),
            ],
          ],
        ),
        const SizedBox(height: 8),
        SizedBox(
          width: double.infinity,
          child: FilledButton.icon(
            onPressed: () => context.go('/bookings'),
            icon: const Icon(Icons.arrow_back_rounded),
            label: const Text('Kembali ke daftar booking'),
          ),
        ),
      ],
    );
  }

  Widget _successBanner(ColorScheme colorScheme) => Container(
    padding: const EdgeInsets.all(16),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [AppColors.primaryDark, AppColors.primary],
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
      ),
      borderRadius: BorderRadius.circular(20),
    ),
    child: Row(
      children: [
        Container(
          width: 48,
          height: 48,
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: 0.14),
            borderRadius: BorderRadius.circular(14),
          ),
          child: const Icon(
            Icons.check_circle_rounded,
            color: Colors.white,
            size: 28,
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Check-in berhasil',
                style: TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w800,
                  fontSize: 16,
                ),
              ),
              const SizedBox(height: 3),
              Text(
                'Bukti check-in ditampilkan pada detail booking ini.',
                style: TextStyle(
                  color: Colors.white.withValues(alpha: 0.78),
                  fontSize: 12,
                  height: 1.35,
                ),
              ),
            ],
          ),
        ),
      ],
    ),
  );

  Widget _infoBanner(
    BuildContext context,
    ColorScheme colorScheme, {
    required IconData icon,
    required String title,
    required String description,
  }) => Container(
    padding: const EdgeInsets.all(16),
    decoration: BoxDecoration(
      color: AppColors.warningSoft,
      borderRadius: BorderRadius.circular(18),
    ),
    child: Row(
      children: [
        Icon(icon, color: AppColors.warning, size: 32),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: const TextStyle(
                  color: AppColors.text,
                  fontWeight: FontWeight.bold,
                  fontSize: 15,
                ),
              ),
              const SizedBox(height: 4),
              Text(description, style: Theme.of(context).textTheme.bodySmall),
            ],
          ),
        ),
      ],
    ),
  );

  Widget _section(
    BuildContext context, {
    required String title,
    required IconData icon,
    required List<Widget> children,
  }) => AppSectionCard(
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            AppIconBox(icon: icon, size: 34),
            const SizedBox(width: 10),
            Text(title, style: Theme.of(context).textTheme.titleMedium),
          ],
        ),
        const SizedBox(height: 14),
        ...children,
      ],
    ),
  );
}

class _ReceiptMetaBar extends StatelessWidget {
  const _ReceiptMetaBar({required this.onRefresh});

  final VoidCallback onRefresh;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Container(
          width: 7,
          height: 7,
          decoration: const BoxDecoration(
            color: AppColors.accent,
            shape: BoxShape.circle,
          ),
        ),
        const SizedBox(width: 6),
        Expanded(
          child: Text(
            'TIKET TERVERIFIKASI OTOMATIS',
            style: Theme.of(context).textTheme.labelSmall,
          ),
        ),
        OutlinedButton.icon(
          onPressed: onRefresh,
          icon: const Icon(Icons.refresh_rounded, size: 16),
          label: const Text('Segarkan'),
          style: OutlinedButton.styleFrom(
            minimumSize: const Size(0, 36),
            padding: const EdgeInsets.symmetric(horizontal: 12),
            backgroundColor: AppColors.surface,
            textStyle: const TextStyle(
              fontSize: 11,
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
      ],
    );
  }
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 5),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          width: 104,
          child: Text(label, style: Theme.of(context).textTheme.bodySmall),
        ),
        Expanded(
          child: Text(
            value,
            style: Theme.of(context).textTheme.bodyMedium
                ?.copyWith(fontWeight: FontWeight.w600),
          ),
        ),
      ],
    ),
  );
}

class _ProofRow extends StatelessWidget {
  const _ProofRow({required this.label, required this.valid, this.detail});

  final String label;
  final bool valid;
  final String? detail;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 7),
      child: Row(
        children: [
          AppIconBox(
            icon: valid ? Icons.verified_rounded : Icons.error_outline_rounded,
            size: 34,
            backgroundColor: valid ? AppColors.mintSoft : AppColors.dangerSoft,
            foregroundColor: valid ? AppColors.primary : AppColors.danger,
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  label,
                  style: Theme.of(context).textTheme.bodyMedium
                      ?.copyWith(fontWeight: FontWeight.w600),
                ),
                if (detail != null) ...[
                  const SizedBox(height: 2),
                  Text(detail!, style: Theme.of(context).textTheme.bodySmall),
                ],
              ],
            ),
          ),
          AppPill(
            label: valid ? 'Terverifikasi' : 'Gagal',
            icon: valid ? Icons.check_rounded : Icons.close_rounded,
            backgroundColor: valid
                ? AppColors.mintStrong
                : AppColors.dangerSoft,
            foregroundColor: valid ? AppColors.primary : AppColors.danger,
          ),
        ],
      ),
    );
  }
}
