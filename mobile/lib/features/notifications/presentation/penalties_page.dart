import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/formatting/formatters.dart';
import '../../../core/widgets/status_views.dart';
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
        loading: () => const Center(
          child: CircularProgressIndicator(semanticsLabel: 'Memuat penalti'),
        ),
        error: (error, _) => ErrorView(
          error: error,
          onRetry: () => ref.invalidate(strikesProvider),
        ),
        data: (items) => items.isEmpty
            ? const EmptyView(
                message: 'Tidak ada strike aktif.',
                icon: Icons.verified_user_outlined,
              )
            : ListView.builder(
                itemCount: items.length,
                itemBuilder: (context, index) {
                  final item = items[index];
                  return ListTile(
                    leading: const Icon(Icons.warning_amber),
                    title: Text('${item['reason'] ?? 'Strike'}'),
                    subtitle: Text(
                      formatWibDateTime(
                        item['created_at'] ?? item['issued_at'],
                      ),
                    ),
                  );
                },
              ),
      ),
    );
  }
}
