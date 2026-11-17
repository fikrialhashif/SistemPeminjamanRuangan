import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

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
  final _location = LocationService();
  String? qrToken;
  LocationEvidence? evidence;
  String? message;
  bool loading = false;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Check-in')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text(qrToken == null ? 'QR belum dipindai' : 'QR berhasil dipindai'),
          const SizedBox(height: 8),
          if (qrToken == null)
            SizedBox(
              height: 260,
              child: MobileScanner(
                onDetect: (capture) {
                  final value = capture.barcodes.firstOrNull?.rawValue;
                  if (value != null && mounted) setState(() => qrToken = value);
                },
              ),
            ),
          const SizedBox(height: 16),
          FilledButton.icon(
            onPressed: loading ? null : _checkIn,
            icon: const Icon(Icons.location_on),
            label: Text(loading ? 'Memproses...' : 'Ambil lokasi dan check-in'),
          ),
          if (message != null)
            Padding(
              padding: const EdgeInsets.only(top: 20),
              child: Text(
                message!,
                style: Theme.of(context).textTheme.bodyLarge,
              ),
            ),
        ],
      ),
    );
  }

  Future<void> _checkIn() async {
    if (qrToken == null) {
      setState(() => message = 'Pindai QR ruangan terlebih dahulu.');
      return;
    }
    setState(() {
      loading = true;
      message = null;
    });
    try {
      final permission = await _location.permissionState();
      if (permission != PermissionState.granted) {
        final requested = await _location.requestPermission();
        if (requested != PermissionState.granted) {
          setState(
            () => message = requested == PermissionState.permanentlyDenied
                ? 'Izin lokasi ditolak permanen. Buka pengaturan aplikasi.'
                : 'Izin lokasi diperlukan untuk check-in.',
          );
          return;
        }
      }
      final location = await _location.currentEvidence();
      final result = await ref
          .read(checkInRepositoryProvider)
          .checkIn(
            bookingId: widget.bookingId,
            qrToken: qrToken!,
            location: location,
          );
      final accepted =
          result['result'] == 'accepted' || result['status'] == 'accepted';
      setState(
        () => message = accepted
            ? 'Check-in berhasil.'
            : checkInReasonMessage(
                result['reason_code']?.toString() ??
                    result['reason']?.toString(),
              ),
      );
    } catch (error) {
      setState(() => message = error.toString());
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }
}
