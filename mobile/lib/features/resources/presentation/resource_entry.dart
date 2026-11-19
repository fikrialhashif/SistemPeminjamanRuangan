import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_error.dart';
import '../../face/data/face_providers.dart';
import '../../face/data/face_repository.dart';

Future<void> openResourceBooking(
  BuildContext context,
  WidgetRef ref,
  String resourceId,
) async {
  if (!await ensureFaceEnrolled(context, ref)) return;
  if (context.mounted) context.push('/bookings/new/$resourceId');
}

Future<bool> openFaceEnrollment(BuildContext context, WidgetRef ref) async {
  final result = await context.push<bool>('/face/enrollment');
  ref.invalidate(faceStatusProvider);
  return result == true;
}

Future<bool> ensureFaceEnrolled(BuildContext context, WidgetRef ref) async {
  try {
    final status = await ref.read(faceRepositoryProvider).status();
    ref.invalidate(faceStatusProvider);
    if (status['enabled'] != true || status['enrolled'] == true) return true;
    if (!context.mounted) return false;

    final enroll = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        icon: const Icon(Icons.face_retouching_natural),
        title: const Text('Daftarkan wajah terlebih dahulu'),
        content: const Text(
          'Wajah wajib terdaftar sebelum membuat booking ruangan. Daftarkan wajah sekarang?',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(false),
            child: const Text('Nanti'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(dialogContext).pop(true),
            child: const Text('Daftar wajah'),
          ),
        ],
      ),
    );
    if (enroll != true) return false;
    if (!context.mounted) return false;
    return await openFaceEnrollment(context, ref);
  } catch (error) {
    if (!context.mounted) return false;
    ScaffoldMessenger.of(context)
        .showSnackBar(SnackBar(content: Text(apiErrorMessage(error))));
    return true;
  }
}
