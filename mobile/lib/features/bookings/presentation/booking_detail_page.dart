import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/formatting/formatters.dart';
import '../../../core/widgets/status_views.dart';
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
      appBar: AppBar(
        title: const Text('Detail booking'),
        actions: [
          IconButton(
            tooltip: 'Muat ulang',
            icon: const Icon(Icons.refresh),
            onPressed: () => ref.invalidate(bookingDetailProvider(bookingId)),
          ),
        ],
      ),
      body: detail.when(
        loading: () => const Center(
          child: CircularProgressIndicator(
            semanticsLabel: 'Memuat detail booking',
          ),
        ),
        error: (error, _) => ErrorView(
          error: error,
          onRetry: () => ref.invalidate(bookingDetailProvider(bookingId)),
        ),
        data: (booking) =>
            _BookingDetailBody(booking: booking, justCheckedIn: justCheckedIn),
      ),
    );
  }
}

class _BookingDetailBody extends StatelessWidget {
  const _BookingDetailBody({
    required this.booking,
    required this.justCheckedIn,
  });

  final Map<String, dynamic> booking;
  final bool justCheckedIn;

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
      padding: const EdgeInsets.all(16),
      children: [
        if (justCheckedIn || accepted) _successBanner(colorScheme),
        if (status == 'pending')
          _infoBanner(
            colorScheme,
            icon: Icons.schedule,
            title: 'Menunggu persetujuan approver',
            description: 'Check-in tersedia setelah booking ini disetujui.',
          ),
        _section(
          title: 'Informasi booking',
          icon: Icons.receipt_long,
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
        _section(
          title: 'Bukti check-in',
          icon: Icons.how_to_reg,
          children: [
            if (latest == null)
              const Padding(
                padding: EdgeInsets.symmetric(vertical: 8),
                child: Text('Belum ada check-in untuk booking ini.'),
              )
            else ...[
              _InfoRow(
                label: 'Waktu',
                value: formatWibDateTime(latest['created_at']),
              ),
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
        const SizedBox(height: 24),
        FilledButton.icon(
          onPressed: () => context.go('/bookings'),
          icon: const Icon(Icons.arrow_back),
          label: const Text('Kembali ke daftar booking'),
        ),
      ],
    );
  }

  Widget _successBanner(ColorScheme colorScheme) => _infoBanner(
    colorScheme,
    icon: Icons.check_circle,
    title: 'Check-in berhasil',
    description: 'Bukti check-in ditampilkan pada detail booking ini.',
  );

  Widget _infoBanner(
    ColorScheme colorScheme, {
    required IconData icon,
    required String title,
    required String description,
  }) => Container(
    margin: const EdgeInsets.only(bottom: 16),
    padding: const EdgeInsets.all(16),
    decoration: BoxDecoration(
      color: colorScheme.primaryContainer,
      borderRadius: BorderRadius.circular(16),
    ),
    child: Row(
      children: [
        Icon(icon, color: colorScheme.onPrimaryContainer, size: 36),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: TextStyle(
                  color: colorScheme.onPrimaryContainer,
                  fontWeight: FontWeight.bold,
                  fontSize: 16,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                description,
                style: TextStyle(color: colorScheme.onPrimaryContainer),
              ),
            ],
          ),
        ),
      ],
    ),
  );

  Widget _section({
    required String title,
    required IconData icon,
    required List<Widget> children,
  }) => Card(
    margin: const EdgeInsets.only(bottom: 16),
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(icon, size: 20),
              const SizedBox(width: 8),
              Text(title, style: const TextStyle(fontWeight: FontWeight.bold)),
            ],
          ),
          const SizedBox(height: 12),
          ...children,
        ],
      ),
    ),
  );
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 4),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          width: 96,
          child: Text(
            label,
            style: Theme.of(context).textTheme.bodySmall
                ?.copyWith(color: Theme.of(context).colorScheme.outline),
          ),
        ),
        Expanded(child: Text(value)),
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
    final colorScheme = Theme.of(context).colorScheme;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        children: [
          Icon(
            valid ? Icons.check_circle : Icons.cancel,
            size: 20,
            color: valid ? colorScheme.primary : colorScheme.error,
          ),
          const SizedBox(width: 10),
          Expanded(child: Text(label)),
          if (detail != null)
            Text(
              detail!,
              style: Theme.of(context).textTheme.bodySmall
                  ?.copyWith(color: colorScheme.outline),
            ),
        ],
      ),
    );
  }
}
