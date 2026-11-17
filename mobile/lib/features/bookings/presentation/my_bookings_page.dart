import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

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
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(child: Text(e.toString())),
        data: (items) => items.isEmpty
            ? const Center(child: Text('Belum ada booking'))
            : RefreshIndicator(
                onRefresh: () => ref.refresh(bookingsProvider.future),
                child: ListView.builder(
                  itemCount: items.length,
                  itemBuilder: (_, index) {
                    final item = items[index];
                    final resource = item['resource'] as Map?;
                    return ListTile(
                      title: Text(
                        item['title']?.toString() ??
                            resource?['name']?.toString() ??
                            'Booking',
                      ),
                      subtitle: Text(item['status']?.toString() ?? '-'),
                      trailing: item['id'] == null
                          ? null
                          : IconButton(
                              icon: const Icon(Icons.cancel_outlined),
                              onPressed: () =>
                                  _cancel(context, ref, item['id'].toString()),
                            ),
                    );
                  },
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
    } catch (error) {
      if (context.mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(error.toString())));
      }
    }
  }
}
