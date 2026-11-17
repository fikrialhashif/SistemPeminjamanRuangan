import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/formatting/formatters.dart';
import '../../../core/network/api_error.dart';
import '../../../core/widgets/status_views.dart';
import '../data/approval_repository.dart';
import 'approval_providers.dart';

class ApprovalsPage extends ConsumerWidget {
  const ApprovalsPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final approvals = ref.watch(pendingApprovalsProvider);
    return Scaffold(
      appBar: AppBar(title: const Text('Persetujuan')),
      body: approvals.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => ErrorView(
          error: error,
          onRetry: () => ref.invalidate(pendingApprovalsProvider),
        ),
        data: (items) => items.isEmpty
            ? const EmptyView(
                message: 'Tidak ada permintaan pending.',
                icon: Icons.approval,
              )
            : RefreshIndicator(
                onRefresh: () => ref.refresh(pendingApprovalsProvider.future),
                child: ListView.builder(
                  itemCount: items.length,
                  itemBuilder: (_, index) =>
                      _ApprovalTile(approval: items[index]),
                ),
              ),
      ),
    );
  }
}

class _ApprovalTile extends ConsumerWidget {
  const _ApprovalTile({required this.approval});
  final Map<String, dynamic> approval;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final booking = approval['booking'] as Map?;
    final resource = booking?['resource'] as Map?;
    final user = booking?['user'] as Map?;
    return ListTile(
      title: Text(resource?['name']?.toString() ?? 'Booking'),
      subtitle: Text(
        '${user?['name'] ?? user?['email'] ?? 'Pengguna'}\n'
        '${formatWibDateTime(booking?['starts_at'])}',
      ),
      isThreeLine: true,
      onTap: () => _showDecision(context, ref),
    );
  }

  Future<void> _showDecision(BuildContext context, WidgetRef ref) async {
    final notes = TextEditingController();
    final decision = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (context) => Padding(
        padding: EdgeInsets.fromLTRB(
          16,
          16,
          16,
          MediaQuery.viewInsetsOf(context).bottom + 16,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Text(
              'Detail permintaan',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: notes,
              maxLength: 1000,
              maxLines: 3,
              decoration: const InputDecoration(
                labelText: 'Catatan (opsional)',
              ),
            ),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: () => Navigator.pop(context, false),
                    child: const Text('Tolak'),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: FilledButton(
                    onPressed: () => Navigator.pop(context, true),
                    child: const Text('Setujui'),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
    final id = approval['id']?.toString();
    if (decision == null || id == null) {
      notes.dispose();
      return;
    }
    try {
      await ref
          .read(approvalRepositoryProvider)
          .decide(id: id, approve: decision, notes: notes.text);
      ref.invalidate(pendingApprovalsProvider);
    } catch (error) {
      if (context.mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(apiErrorMessage(error))));
      }
    } finally {
      notes.dispose();
    }
  }
}
