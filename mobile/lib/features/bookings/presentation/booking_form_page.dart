import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_error.dart';
import '../../face/data/face_providers.dart';
import '../../face/data/face_repository.dart';
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
  bool faceLoading = true;
  bool faceEnabled = false;
  bool faceEnrolled = false;

  @override
  void initState() {
    super.initState();
    _loadFaceStatus();
  }

  @override
  void dispose() {
    title.dispose();
    notes.dispose();
    super.dispose();
  }

  Future<void> _loadFaceStatus() async {
    try {
      final status = await ref.read(faceRepositoryProvider).status();
      if (!mounted) return;
      setState(() {
        faceEnabled = status['enabled'] == true;
        faceEnrolled = status['enrolled'] == true;
        faceLoading = false;
      });
    } catch (_) {
      if (mounted) setState(() => faceLoading = false);
    }
  }

  Future<void> _openEnrollment() async {
    final result = await context.push<bool>('/face/enrollment');
    ref.invalidate(faceStatusProvider);
    if (!mounted) return;
    if (result == true) {
      setState(() {
        faceEnabled = true;
        faceEnrolled = true;
      });
    } else {
      await _loadFaceStatus();
    }
  }

  @override
  Widget build(BuildContext context) {
    final availability = ref.watch(
      availabilityProvider(AvailabilityQuery(widget.resourceId, date)),
    );
    final needsEnrollment = faceEnabled && !faceEnrolled;
    final scheme = Theme.of(context).colorScheme;
    return Scaffold(
      appBar: AppBar(title: const Text('Buat booking')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          if (needsEnrollment) ...[
            Material(
              color: scheme.secondaryContainer,
              borderRadius: BorderRadius.circular(16),
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Row(
                      children: [
                        Icon(Icons.face_retouching_natural),
                        SizedBox(width: 12),
                        Expanded(
                          child: Text(
                            'Wajah belum terdaftar',
                            style: TextStyle(fontWeight: FontWeight.w600),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    const Text(
                      'Daftarkan wajah terlebih dahulu sebelum membuat booking ruangan.',
                    ),
                    const SizedBox(height: 12),
                    FilledButton.icon(
                      onPressed: _openEnrollment,
                      icon: const Icon(Icons.camera_front),
                      label: const Text('Daftar wajah sekarang'),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 16),
          ],
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
            error: (e, _) => Text(apiErrorMessage(e)),
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
            onPressed: loading || faceLoading || needsEnrollment
                ? null
                : _submit,
            child: loading || faceLoading
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
    if (faceEnabled && !faceEnrolled) {
      setState(
        () => error =
            'Daftarkan wajah terlebih dahulu sebelum membuat booking ruangan.',
      );
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
      if (!mounted) return;
      if (e is ApiError && e.code == 'face_enrollment_required') {
        setState(() {
          faceEnabled = true;
          faceEnrolled = false;
        });
      }
      setState(() => error = apiErrorMessage(e));
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }
}
