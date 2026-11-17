import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_error.dart';

import '../data/advanced_booking_repository.dart';

class BundledBookingPage extends ConsumerStatefulWidget {
  const BundledBookingPage({super.key});
  @override
  ConsumerState<BundledBookingPage> createState() => _BundledBookingPageState();
}

class _BundledBookingPageState extends ConsumerState<BundledBookingPage> {
  final resources = TextEditingController();
  final starts = TextEditingController();
  final ends = TextEditingController();
  bool busy = false;
  String? error;

  Future<void> submit() async {
    final ids = resources.text
        .split(',')
        .map((value) => int.tryParse(value.trim()))
        .whereType<int>()
        .toList();
    if (ids.length < 2) {
      setState(() => error = 'Masukkan minimal 2 ID resource.');
      return;
    }
    setState(() {
      busy = true;
      error = null;
    });
    try {
      await ref.read(advancedBookingRepositoryProvider).bundled({
        'resource_ids': ids,
        'starts_at': starts.text,
        'ends_at': ends.text,
      });
      if (mounted) Navigator.pop(context);
    } catch (e) {
      setState(() => error = apiErrorMessage(e));
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Booking bundled')),
    body: ListView(
      padding: const EdgeInsets.all(16),
      children: [
        TextField(
          controller: resources,
          decoration: const InputDecoration(
            labelText: 'ID resource (pisahkan koma)',
          ),
        ),
        TextField(
          controller: starts,
          decoration: const InputDecoration(labelText: 'Mulai (ISO 8601)'),
        ),
        TextField(
          controller: ends,
          decoration: const InputDecoration(labelText: 'Selesai (ISO 8601)'),
        ),
        if (error != null)
          Text(
            error!,
            style: TextStyle(color: Theme.of(context).colorScheme.error),
          ),
        ElevatedButton(
          onPressed: busy ? null : submit,
          child: Text(busy ? 'Memproses...' : 'Buat bundled booking'),
        ),
      ],
    ),
  );
}
