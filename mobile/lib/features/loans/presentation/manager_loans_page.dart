import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../data/loan_repository.dart';
import 'override_page.dart';

final loansProvider = FutureProvider.autoDispose<List<Map<String, dynamic>>>((
  ref,
) {
  return ref.read(loanRepositoryProvider).list();
});

class ManagerLoansPage extends ConsumerWidget {
  const ManagerLoansPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final loans = ref.watch(loansProvider);
    return Scaffold(
      appBar: AppBar(title: const Text('Peminjaman aset')),
      body: loans.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => Center(child: Text('Gagal memuat: $error')),
        data: (items) => items.isEmpty
            ? const Center(child: Text('Belum ada peminjaman.'))
            : ListView.builder(
                itemCount: items.length,
                itemBuilder: (context, index) {
                  final loan = items[index];
                  final id = '${loan['id']}';
                  final status = '${loan['status'] ?? '-'}';
                  final asset = Map<String, dynamic>.from(
                    loan['asset'] as Map? ?? {},
                  );
                  return ListTile(
                    title: Text('${asset['code'] ?? 'Aset'}'),
                    subtitle: Text('Status: $status'),
                    trailing: TextButton(
                      onPressed: () => Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (_) =>
                              LoanActionPage(loanId: id, status: status),
                        ),
                      ),
                      child: const Text('Kelola'),
                    ),
                  );
                },
              ),
      ),
    );
  }
}

class LoanActionPage extends StatelessWidget {
  const LoanActionPage({required this.loanId, required this.status, super.key});
  final String loanId;
  final String status;

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: Text('Peminjaman #$loanId')),
    body: Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text('Status dari server: $status'),
          const SizedBox(height: 16),
          if (status == 'reserved')
            ElevatedButton(
              onPressed: () => Navigator.of(context).push(
                MaterialPageRoute(builder: (_) => HandoverPage(loanId: loanId)),
              ),
              child: const Text('Serah-terima aset'),
            ),
          if (status == 'reserved')
            OutlinedButton(
              onPressed: () => Navigator.of(context).push(
                MaterialPageRoute(builder: (_) => OverridePage(loanId: loanId)),
              ),
              child: const Text('Override manual'),
            ),
          if (status == 'handed_over' || status == 'in_use')
            ElevatedButton(
              onPressed: () => Navigator.of(context).push(
                MaterialPageRoute(builder: (_) => ReturnPage(loanId: loanId)),
              ),
              child: const Text('Kembalikan aset'),
            ),
        ],
      ),
    ),
  );
}

class HandoverPage extends ConsumerStatefulWidget {
  const HandoverPage({required this.loanId, super.key});
  final String loanId;
  @override
  ConsumerState<HandoverPage> createState() => _HandoverPageState();
}

class _HandoverPageState extends ConsumerState<HandoverPage> {
  final code = TextEditingController();
  final face = TextEditingController();
  bool busy = false;
  String? error;

  Future<void> submit() async {
    setState(() {
      busy = true;
      error = null;
    });
    try {
      await ref
          .read(loanRepositoryProvider)
          .handover(
            loanId: widget.loanId,
            assetCode: code.text,
            faceImage: face.text,
            latitude: 0,
            longitude: 0,
            accuracy: 0,
          );
      if (mounted) Navigator.pop(context);
    } catch (e) {
      setState(() => error = '$e');
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Serah-terima')),
    body: Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        children: [
          TextField(
            controller: code,
            decoration: const InputDecoration(labelText: 'Kode aset'),
          ),
          TextField(
            controller: face,
            decoration: const InputDecoration(labelText: 'Bukti wajah'),
          ),
          if (error != null)
            Text(
              error!,
              style: TextStyle(color: Theme.of(context).colorScheme.error),
            ),
          ElevatedButton(
            onPressed: busy ? null : submit,
            child: Text(busy ? 'Memproses...' : 'Verifikasi dan serahkan'),
          ),
        ],
      ),
    ),
  );
}

class ReturnPage extends ConsumerStatefulWidget {
  const ReturnPage({required this.loanId, super.key});
  final String loanId;
  @override
  ConsumerState<ReturnPage> createState() => _ReturnPageState();
}

class _ReturnPageState extends ConsumerState<ReturnPage> {
  final notes = TextEditingController();
  String condition = 'good';
  bool busy = false;
  String? error;

  Future<void> submit() async {
    setState(() {
      busy = true;
      error = null;
    });
    try {
      await ref
          .read(loanRepositoryProvider)
          .returnAsset(
            loanId: widget.loanId,
            condition: condition,
            notes: notes.text,
          );
      if (mounted) Navigator.pop(context);
    } catch (e) {
      setState(() => error = '$e');
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Pengembalian')),
    body: Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        children: [
          DropdownButtonFormField(
            initialValue: condition,
            items: const [
              DropdownMenuItem(value: 'good', child: Text('Baik')),
              DropdownMenuItem(value: 'damaged', child: Text('Rusak')),
              DropdownMenuItem(value: 'lost', child: Text('Hilang')),
            ],
            onChanged: (value) => setState(() => condition = value!),
            decoration: const InputDecoration(labelText: 'Kondisi'),
          ),
          TextField(
            controller: notes,
            decoration: const InputDecoration(labelText: 'Catatan kerusakan'),
          ),
          if (error != null)
            Text(
              error!,
              style: TextStyle(color: Theme.of(context).colorScheme.error),
            ),
          ElevatedButton(
            onPressed: busy ? null : submit,
            child: Text(busy ? 'Mengunggah...' : 'Simpan pengembalian'),
          ),
        ],
      ),
    ),
  );
}
