import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import '../../../core/formatting/formatters.dart';
import '../../../core/network/api_error.dart';
import '../../../core/widgets/status_views.dart';
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
        loading: () => const Center(
          child: CircularProgressIndicator(semanticsLabel: 'Memuat peminjaman'),
        ),
        error: (error, _) => ErrorView(
          error: error,
          onRetry: () => ref.invalidate(loansProvider),
        ),
        data: (items) => items.isEmpty
            ? const EmptyView(
                message: 'Belum ada peminjaman.',
                icon: Icons.inventory_2_outlined,
              )
            : ListView.builder(
                itemCount: items.length,
                itemBuilder: (context, index) {
                  final loan = items[index];
                  final id = '${loan['id']}';
                  final status = loan['status']?.toString() ?? '-';
                  final asset = Map<String, dynamic>.from(
                    loan['asset'] as Map? ?? {},
                  );
                  return ListTile(
                    title: Text('${asset['code'] ?? 'Aset'}'),
                    subtitle: Text('Status: ${statusLabel(status)}'),
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
  bool scanning = false;
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
      setState(() => error = apiErrorMessage(e));
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
          if (scanning)
            SizedBox(
              height: 220,
              child: MobileScanner(
                onDetect: (capture) {
                  final value = capture.barcodes.firstOrNull?.rawValue;
                  if (value != null && mounted) {
                    setState(() {
                      code.text = value;
                      scanning = false;
                    });
                  }
                },
              ),
            )
          else
            OutlinedButton.icon(
              onPressed: () => setState(() => scanning = true),
              icon: const Icon(Icons.qr_code_scanner),
              label: const Text('Pindai label aset'),
            ),
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
  final assetCode = TextEditingController();
  String condition = 'good';
  bool scanning = false;
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
            assetCode: assetCode.text.trim().isEmpty
                ? null
                : assetCode.text.trim(),
            notes: notes.text,
          );
      if (mounted) Navigator.pop(context);
    } catch (e) {
      setState(() => error = apiErrorMessage(e));
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
          if (scanning)
            SizedBox(
              height: 220,
              child: MobileScanner(
                onDetect: (capture) {
                  final value = capture.barcodes.firstOrNull?.rawValue;
                  if (value != null && mounted) {
                    setState(() {
                      assetCode.text = value;
                      scanning = false;
                    });
                  }
                },
              ),
            )
          else
            OutlinedButton.icon(
              onPressed: () => setState(() => scanning = true),
              icon: const Icon(Icons.qr_code_scanner),
              label: const Text('Pindai label aset'),
            ),
          TextField(
            controller: assetCode,
            decoration: const InputDecoration(
              labelText: 'Kode aset (opsional)',
            ),
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
