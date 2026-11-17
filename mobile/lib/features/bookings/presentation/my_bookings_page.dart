import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/formatting/formatters.dart';
import '../../../core/network/api_error.dart';
import '../../../core/widgets/status_views.dart';
import '../data/booking_repository.dart';
import 'booking_providers.dart';

class MyBookingsPage extends ConsumerWidget {
  const MyBookingsPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final bookings = ref.watch(bookingsProvider);
    return Scaffold(
      appBar: AppBar(title: const Text('Booking saya')),
      body: bookings.when(
        loading: () => const Center(
          child: CircularProgressIndicator(semanticsLabel: 'Memuat booking'),
        ),
        error: (error, _) => ErrorView(
          error: error,
          onRetry: () => ref.invalidate(bookingsProvider),
        ),
        data: (items) => items.isEmpty
            ? const EmptyView(
                message: 'Belum ada booking.',
                icon: Icons.event_available,
              )
            : RefreshIndicator(
                onRefresh: () => ref.refresh(bookingsProvider.future),
                child: ListView.builder(
                  itemCount: items.length,
                  itemBuilder: (_, index) =>
                      _BookingTile(booking: items[index]),
                ),
              ),
      ),
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
    return ListTile(
      title: Text(
        booking['title']?.toString() ??
            resource?['name']?.toString() ??
            'Booking',
      ),
      subtitle: Text(
        '${statusLabel(status)} • ${formatWibDateTime(booking['starts_at'])}',
      ),
      isThreeLine: false,
      trailing: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (canCheckIn && id != null)
            IconButton(
              tooltip: 'Check-in',
              icon: const Icon(Icons.how_to_reg),
              onPressed: () => context.push('/bookings/$id/check-in'),
            ),
          if (canCancel && id != null)
            IconButton(
              tooltip: 'Batalkan booking',
              icon: const Icon(Icons.cancel_outlined),
              onPressed: () => _cancel(context, ref, id),
            ),
        ],
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
