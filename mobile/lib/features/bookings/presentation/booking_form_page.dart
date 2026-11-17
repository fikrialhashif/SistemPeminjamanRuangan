import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../data/booking_repository.dart';
import 'booking_providers.dart';

class BookingFormPage extends ConsumerStatefulWidget {
  const BookingFormPage({required this.resourceId, super.key});
  final String resourceId;
  @override
  ConsumerState<BookingFormPage> createState() => _BookingFormPageState();
}

class _BookingFormPageState extends ConsumerState<BookingFormPage> {
  DateTime date = DateTime.now();
  TimeOfDay? start;
  TimeOfDay? end;
  final title = TextEditingController();
  final notes = TextEditingController();
  String? error;
  bool loading = false;

  @override
  void dispose() {
    title.dispose();
    notes.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final availability = ref.watch(
      availabilityProvider(AvailabilityQuery(widget.resourceId, date)),
    );
    return Scaffold(
      appBar: AppBar(title: const Text('Buat booking')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          ListTile(
            title: Text('${date.day}/${date.month}/${date.year}'),
            trailing: const Icon(Icons.calendar_month),
            onTap: () async {
              final picked = await showDatePicker(
                context: context,
                firstDate: DateTime.now(),
                lastDate: DateTime.now().add(const Duration(days: 365)),
                initialDate: date,
              );
              if (picked != null) setState(() => date = picked);
            },
          ),
          availability.when(
            loading: () => const LinearProgressIndicator(),
            error: (e, _) => Text(e.toString()),
            data: (slots) => Text('${slots.length} slot tersedia'),
          ),
          ListTile(
            title: Text(start == null ? 'Pilih mulai' : start!.format(context)),
            onTap: () async {
              final picked = await showTimePicker(
                context: context,
                initialTime: start ?? TimeOfDay.now(),
              );
              if (picked != null) setState(() => start = picked);
            },
          ),
          ListTile(
            title: Text(end == null ? 'Pilih selesai' : end!.format(context)),
            onTap: () async {
              final picked = await showTimePicker(
                context: context,
                initialTime: end ?? TimeOfDay.now(),
              );
              if (picked != null) setState(() => end = picked);
            },
          ),
          TextField(
            controller: title,
            decoration: const InputDecoration(labelText: 'Judul'),
          ),
          TextField(
            controller: notes,
            decoration: const InputDecoration(labelText: 'Catatan'),
          ),
          if (error != null)
            Padding(
              padding: const EdgeInsets.only(top: 12),
              child: Text(
                error!,
                style: TextStyle(color: Theme.of(context).colorScheme.error),
              ),
            ),
          const SizedBox(height: 16),
          FilledButton(
            onPressed: loading ? null : _submit,
            child: loading
                ? const CircularProgressIndicator()
                : const Text('Booking'),
          ),
        ],
      ),
    );
  }

  Future<void> _submit() async {
    if (start == null || end == null) {
      setState(() => error = 'Waktu mulai dan selesai wajib dipilih.');
      return;
    }
    final starts = DateTime(
      date.year,
      date.month,
      date.day,
      start!.hour,
      start!.minute,
    );
    final ends = DateTime(
      date.year,
      date.month,
      date.day,
      end!.hour,
      end!.minute,
    );
    if (!ends.isAfter(starts)) {
      setState(() => error = 'Waktu selesai harus setelah mulai.');
      return;
    }
    setState(() {
      loading = true;
      error = null;
    });
    try {
      await ref
          .read(bookingRepositoryProvider)
          .create(
            resourceId: widget.resourceId,
            startsAt: starts,
            endsAt: ends,
            title: title.text,
            notes: notes.text,
          );
      if (mounted) Navigator.pop(context);
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }
}
