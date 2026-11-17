import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../data/advanced_booking_repository.dart';

class RecurringBookingPage extends ConsumerStatefulWidget {
  const RecurringBookingPage({required this.resourceId, super.key});
  final String resourceId;
  @override
  ConsumerState<RecurringBookingPage> createState() =>
      _RecurringBookingPageState();
}

class _RecurringBookingPageState extends ConsumerState<RecurringBookingPage> {
  final starts = TextEditingController();
  final ends = TextEditingController();
  final occurrences = TextEditingController(text: '4');
  String policy = 'skip';
  bool busy = false;
  String? error;

  Future<void> submit() async {
    setState(() {
      busy = true;
      error = null;
    });
    try {
      await ref.read(advancedBookingRepositoryProvider).recurring({
        'resource_id': widget.resourceId,
        'starts_at': starts.text,
        'ends_at': ends.text,
        'occurrences': int.tryParse(occurrences.text) ?? 0,
        'conflict_policy': policy,
      });
      if (mounted) Navigator.pop(context);
    } catch (e) {
      setState(() => error = '$e');
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Booking berulang')),
    body: ListView(
      padding: const EdgeInsets.all(16),
      children: [
        TextField(
          controller: starts,
          decoration: const InputDecoration(labelText: 'Mulai (ISO 8601)'),
        ),
        TextField(
          controller: ends,
          decoration: const InputDecoration(labelText: 'Selesai (ISO 8601)'),
        ),
        TextField(
          controller: occurrences,
          keyboardType: TextInputType.number,
          decoration: const InputDecoration(labelText: 'Jumlah kemunculan'),
        ),
        DropdownButtonFormField(
          initialValue: policy,
          items: const [
            DropdownMenuItem(value: 'skip', child: Text('Lewati konflik')),
            DropdownMenuItem(
              value: 'reject',
              child: Text('Tolak seluruh seri'),
            ),
          ],
          onChanged: (value) => setState(() => policy = value!),
          decoration: const InputDecoration(labelText: 'Kebijakan konflik'),
        ),
        if (error != null)
          Text(
            error!,
            style: TextStyle(color: Theme.of(context).colorScheme.error),
          ),
        ElevatedButton(
          onPressed: busy ? null : submit,
          child: Text(busy ? 'Menyimpan...' : 'Buat seri'),
        ),
      ],
    ),
  );
}
