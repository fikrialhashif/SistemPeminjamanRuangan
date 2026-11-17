import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../data/notification_repository.dart';

final strikesProvider = FutureProvider.autoDispose<List<Map<String, dynamic>>>((
  ref,
) async {
  final response = await ref.read(notificationRepositoryProvider).listStrikes();
  return response;
});

class PenaltiesPage extends ConsumerWidget {
  const PenaltiesPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final strikes = ref.watch(strikesProvider);
    return Scaffold(
      appBar: AppBar(title: const Text('Status penalti')),
      body: strikes.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => Center(child: Text('Gagal memuat: $error')),
        data: (items) => items.isEmpty
            ? const Center(child: Text('Tidak ada strike aktif.'))
            : ListView.builder(
                itemCount: items.length,
                itemBuilder: (context, index) {
                  final item = items[index];
                  return ListTile(
                    leading: const Icon(Icons.warning_amber),
                    title: Text('${item['reason'] ?? 'Strike'}'),
                    subtitle: Text('${item['created_at'] ?? ''}'),
                  );
                },
              ),
      ),
    );
  }
}
