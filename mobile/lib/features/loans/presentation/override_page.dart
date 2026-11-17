import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../data/loan_repository.dart';

class OverridePage extends ConsumerStatefulWidget {
  const OverridePage({required this.loanId, super.key});
  final String loanId;
  @override
  ConsumerState<OverridePage> createState() => _OverridePageState();
}

class _OverridePageState extends ConsumerState<OverridePage> {
  final reason = TextEditingController();
  bool busy = false;
  String? error;

  Future<void> submit() async {
    if (reason.text.trim().length < 10) {
      setState(() => error = 'Alasan minimal 10 karakter.');
      return;
    }
    setState(() {
      busy = true;
      error = null;
    });
    try {
      await ref
          .read(loanRepositoryProvider)
          .overrideVerification(
            loanId: widget.loanId,
            reason: reason.text.trim(),
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
    appBar: AppBar(title: const Text('Override verifikasi')),
    body: Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        children: [
          TextField(
            controller: reason,
            minLines: 3,
            maxLines: 5,
            decoration: const InputDecoration(
              labelText: 'Alasan wajib',
              hintText: 'Jelaskan alasan override',
            ),
          ),
          if (error != null)
            Text(
              error!,
              style: TextStyle(color: Theme.of(context).colorScheme.error),
            ),
          ElevatedButton(
            onPressed: busy ? null : submit,
            child: Text(busy ? 'Mengirim...' : 'Kirim override'),
          ),
        ],
      ),
    ),
  );
}
