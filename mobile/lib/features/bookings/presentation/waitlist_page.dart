import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_error.dart';

import '../data/advanced_booking_repository.dart';

class WaitlistPage extends ConsumerStatefulWidget {
  const WaitlistPage({required this.bookingId, super.key});
  final String bookingId;
  @override
  ConsumerState<WaitlistPage> createState() => _WaitlistPageState();
}

class _WaitlistPageState extends ConsumerState<WaitlistPage> {
  bool busy = false;
  String? result;

  Future<void> join() async {
    setState(() {
      busy = true;
      result = null;
    });
    try {
      await ref
          .read(advancedBookingRepositoryProvider)
          .joinWaitlist(widget.bookingId);
      setState(() => result = 'Berhasil masuk antrean.');
    } catch (e) {
      setState(() => result = apiErrorMessage(e));
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Waitlist')),
    body: Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Text(
              'Slot penuh. Bergabung untuk menerima tawaran saat tersedia.',
            ),
            if (result != null)
              Padding(padding: const EdgeInsets.all(12), child: Text(result!)),
            ElevatedButton(
              onPressed: busy ? null : join,
              child: Text(busy ? 'Memproses...' : 'Gabung antrean'),
            ),
          ],
        ),
      ),
    ),
  );
}
