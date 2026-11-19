import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/formatting/formatters.dart';
import '../../../core/network/api_error.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/widgets/app_chrome.dart';
import '../../../core/widgets/app_components.dart';
import '../../../core/widgets/status_views.dart';
import '../../../core/widgets/user_quick_access_sheet.dart';
import '../data/booking_repository.dart';
import 'booking_providers.dart';

class MyBookingsPage extends ConsumerWidget {
  const MyBookingsPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final bookings = ref.watch(bookingsProvider);
    return Scaffold(
      body: SafeArea(
        bottom: false,
        child: Column(
          children: [
            AppBrandHeader(
              section: '04 Booking Saya',
              onAvatarTap: () => showUserQuickAccessSheet(context),
            ),
            Expanded(
              child: bookings.when(
                loading: () => const Center(
                  child: CircularProgressIndicator(
                    semanticsLabel: 'Memuat booking',
                  ),
                ),
                error: (error, _) => ErrorView(
                  error: error,
                  onRetry: () => ref.invalidate(bookingsProvider),
                ),
                data: (items) => RefreshIndicator(
                  onRefresh: () => ref.refresh(bookingsProvider.future),
                  child: ListView(
                    padding: const EdgeInsets.fromLTRB(18, 18, 18, 24),
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              'Booking saya',
                              style: Theme.of(context).textTheme.headlineSmall,
                            ),
                          ),
                          AppPill(
                            label: '${items.length} Reservasi',
                            backgroundColor: AppColors.mintStrong,
                          ),
                        ],
                      ),
                      const SizedBox(height: 14),
                      if (items.isEmpty)
                        const SizedBox(
                          height: 320,
                          child: EmptyView(
                            message: 'Belum ada booking.',
                            icon: Icons.event_available,
                          ),
                        )
                      else
                        for (final item in items) ...[
                          _BookingTile(booking: item),
                          const SizedBox(height: 12),
                        ],
                    ],
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
      bottomNavigationBar: const AppBottomNav(currentIndex: 2),
    );
  }
}

class _BookingTile extends ConsumerWidget {
  const _BookingTile({required this.booking});

  final Map<String, dynamic> booking;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final resource = booking['resource'] as Map?;
    final status = booking['status']?.toString();
    final id = booking['id']?.toString();
    final canCancel = status == 'pending' || status == 'approved';
    final canCheckIn = status == 'approved';
    final title =
        booking['title']?.toString() ??
        resource?['name']?.toString() ??
        'Booking';

    return Material(
      color: AppColors.surface,
      borderRadius: BorderRadius.circular(18),
      child: InkWell(
        borderRadius: BorderRadius.circular(18),
        onTap: id == null ? null : () => context.push('/bookings/$id'),
        child: Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(18),
            border: Border.all(color: AppColors.border),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const AppIconBox(
                    icon: Icons.business_center_outlined,
                    size: 42,
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          title,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: Theme.of(context).textTheme.titleMedium,
                        ),
                        const SizedBox(height: 3),
                        Row(
                          children: [
                            const Icon(
                              Icons.schedule_rounded,
                              size: 13,
                              color: AppColors.muted,
                            ),
                            const SizedBox(width: 4),
                            Expanded(
                              child: Text(
                                formatWibDateTime(booking['starts_at']),
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: Theme.of(context).textTheme.bodySmall,
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 8),
                  StatusPill(status: status),
                ],
              ),
              if (canCheckIn || canCancel) ...[
                const SizedBox(height: 10),
                Row(
                  mainAxisAlignment: MainAxisAlignment.end,
                  children: [
                    if (canCheckIn && id != null) ...[
                      AppRoundAction(
                        icon: Icons.how_to_reg_rounded,
                        tooltip: 'Check-in',
                        onPressed: () => context.push('/bookings/$id/check-in'),
                      ),
                      const SizedBox(width: 8),
                    ],
                    if (canCancel && id != null)
                      AppRoundAction(
                        icon: Icons.close_rounded,
                        tooltip: 'Batalkan booking',
                        foregroundColor: AppColors.danger,
                        backgroundColor: AppColors.dangerSoft,
                        onPressed: () => _cancel(context, ref, id),
                      ),
                  ],
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _cancel(BuildContext context, WidgetRef ref, String id) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Batalkan booking?'),
        content: const Text('Tindakan ini tidak dapat dibatalkan.'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Tidak'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Batalkan'),
          ),
        ],
      ),
    );
    if (confirmed != true) return;
    try {
      await ref.read(bookingRepositoryProvider).cancel(id);
      ref.invalidate(bookingsProvider);
      if (context.mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(const SnackBar(content: Text('Booking dibatalkan.')));
      }
    } catch (error) {
      if (context.mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(apiErrorMessage(error))));
      }
    }
  }
}
