import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import '../../../core/network/api_error.dart';
import '../../../core/permissions/app_permissions.dart';
import '../data/check_in_repository.dart';
import '../data/location_service.dart';

class CheckInPage extends ConsumerStatefulWidget {
  const CheckInPage({required this.bookingId, super.key});
  final String bookingId;

  @override
  ConsumerState<CheckInPage> createState() => _CheckInPageState();
}

class _CheckInPageState extends ConsumerState<CheckInPage> {
  String? qrToken;
  String? message;
  bool? accepted;
  bool loading = false;

  @override
  Widget build(BuildContext context) {
    final colorScheme = Theme.of(context).colorScheme;
    return Scaffold(
      appBar: AppBar(title: const Text('Check-in')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Row(
            children: [
              Icon(
                qrToken == null
                    ? Icons.qr_code_scanner
                    : Icons.check_circle_outline,
                color: qrToken == null ? null : colorScheme.primary,
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  qrToken == null
                      ? 'Pindai QR yang ditampilkan di ruangan.'
                      : 'QR berhasil dipindai.',
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          if (qrToken == null)
            ClipRRect(
              borderRadius: BorderRadius.circular(12),
              child: SizedBox(
                height: 260,
                child: MobileScanner(
                  onDetect: (capture) {
                    final value = capture.barcodes.firstOrNull?.rawValue;
                    if (value != null && mounted) {
                      setState(() => qrToken = value);
                    }
                  },
                ),
              ),
            ),
          const SizedBox(height: 16),
          FilledButton.icon(
            onPressed: loading ? null : _checkIn,
            icon: loading
                ? const SizedBox.square(
                    dimension: 18,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(Icons.location_on),
            label: Text(loading ? 'Memproses...' : 'Ambil lokasi dan check-in'),
          ),
          if (message != null)
            Semantics(
              liveRegion: true,
              child: Container(
                margin: const EdgeInsets.only(top: 20),
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: (accepted ?? false)
                      ? colorScheme.primaryContainer
                      : colorScheme.errorContainer,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Icon(
                      (accepted ?? false) ? Icons.check_circle : Icons.error,
                      color: (accepted ?? false)
                          ? colorScheme.onPrimaryContainer
                          : colorScheme.onErrorContainer,
                    ),
                    const SizedBox(width: 12),
                    Expanded(child: Text(message!)),
                  ],
                ),
              ),
            ),
        ],
      ),
    );
  }

  Future<void> _checkIn() async {
    if (qrToken == null) {
      setState(() {
        message = 'Pindai QR ruangan terlebih dahulu.';
        accepted = false;
      });
      return;
    }
    setState(() {
      loading = true;
      message = null;
    });
    final locationService = ref.read(locationServiceProvider);
    try {
      final permission = await locationService.permissionState();
      if (permission != PermissionState.granted) {
        final requested = await locationService.requestPermission();
        if (requested != PermissionState.granted) {
          setState(() {
            accepted = false;
            message = requested == PermissionState.permanentlyDenied
                ? 'Izin lokasi ditolak permanen. Buka pengaturan aplikasi lalu izinkan lokasi.'
                : 'Izin lokasi diperlukan untuk check-in.';
          });
          return;
        }
      }
      final location = await locationService.currentEvidence();
      final result = await ref
          .read(checkInRepositoryProvider)
          .checkIn(
            bookingId: widget.bookingId,
            qrToken: qrToken!,
            location: location,
          );
      final state =
          result['result']?.toString() ?? result['status']?.toString();
      final isAccepted = state == 'accepted';
      setState(() {
        accepted = isAccepted;
        message = checkInDecisionMessage(result);
      });
    } catch (error) {
      setState(() {
        accepted = false;
        message = apiErrorMessage(error);
      });
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }
}
