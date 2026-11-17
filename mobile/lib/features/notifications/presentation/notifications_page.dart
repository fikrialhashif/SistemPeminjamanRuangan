import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../data/notification_repository.dart';

final notificationsProvider =
    FutureProvider.autoDispose<List<Map<String, dynamic>>>((ref) {
      return ref.read(notificationRepositoryProvider).list();
    });

class NotificationsPage extends ConsumerWidget {
  const NotificationsPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final notifications = ref.watch(notificationsProvider);
    return Scaffold(
      appBar: AppBar(title: const Text('Notifikasi')),
      body: notifications.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => Center(child: Text('Gagal memuat: $error')),
        data: (items) => items.isEmpty
            ? const Center(child: Text('Belum ada notifikasi.'))
            : ListView.builder(
                itemCount: items.length,
                itemBuilder: (context, index) {
                  final item = items[index];
                  final data = Map<String, dynamic>.from(
                    item['data'] as Map? ?? {},
                  );
                  return ListTile(
                    title: Text('${item['type'] ?? 'Notifikasi'}'),
                    subtitle: Text(
                      '${data['message'] ?? data['reason'] ?? ''}',
                    ),
                  );
                },
              ),
      ),
    );
  }
}
