import 'dart:async';
import 'dart:convert';
import 'dart:typed_data';

import 'package:camera/camera.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_mlkit_face_detection/google_mlkit_face_detection.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import 'package:permission_handler/permission_handler.dart';

import '../../../core/formatting/formatters.dart';
import '../../../core/network/api_error.dart';
import '../../../core/permissions/app_permissions.dart';
import '../../../core/widgets/camera_preview_box.dart';
import '../../../core/verification/verification_steps.dart';
import '../../../core/widgets/status_views.dart';
import '../../check_in/data/location_service.dart';
import '../../face/data/face_guidance.dart';
import '../../face/data/face_repository.dart';
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
  static const _frameSampling = 3;
  static const _maxAutoCaptureAttempts = 2;

  int step = 0;
  final assetToken = TextEditingController();
  LocationEvidence? location;
  String? locationError;
  bool locationLoading = false;
  DateTime? timeReviewedAt;
  Uint8List? faceFrame;
  CameraController? faceCamera;
  FaceDetector? _faceDetector;
  final FaceGuidanceTracker _faceGuidance = FaceGuidanceTracker();
  bool scanning = false;
  bool faceLoading = false;
  bool capturingFace = false;
  bool processingFrame = false;
  bool faceDetected = false;
  double? faceYaw;
  int _frameCounter = 0;
  int _captureAttempts = 0;
  bool busy = false;
  String? error;
  String? faceStatus;

  @override
  void dispose() {
    unawaited(_stopFaceDetection());
    unawaited(_faceDetector?.close() ?? Future<void>.value());
    faceFrame = null;
    faceCamera?.dispose();
    assetToken.dispose();
    super.dispose();
  }

  Future<void> submit() async {
    final token = assetToken.text.trim();
    if (step != 3 || location == null) {
      setState(() => error = 'Ambil lokasi terlebih dahulu.');
      return;
    }
    if (token.isEmpty) {
      setState(() => error = 'Pindai label aset terlebih dahulu.');
      return;
    }
    if (faceFrame == null) {
      setState(() => error = 'Ambil wajah terlebih dahulu.');
      return;
    }

    await _stopFaceDetection();
    setState(() {
      busy = true;
      error = null;
    });
    if (!await _refreshLocationEvidence()) {
      if (mounted) setState(() => busy = false);
      return;
    }
    final evidence = location!;
    try {
      await ref
          .read(loanRepositoryProvider)
          .handover(
            loanId: widget.loanId,
            assetToken: token,
            faceImage: base64Encode(faceFrame!),
            latitude: evidence.latitude,
            longitude: evidence.longitude,
            accuracy: evidence.accuracy,
            gpsTimestamp: evidence.timestamp,
            gpsIsMock: evidence.isMock,
          );
      if (mounted) Navigator.pop(context);
    } catch (e) {
      setState(() => error = apiErrorMessage(e));
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> _captureLocation() async {
    setState(() {
      locationLoading = true;
      locationError = null;
      location = null;
    });
    try {
      final service = ref.read(locationServiceProvider);
      var permission = await service.permissionState();
      if (permission != PermissionState.granted) {
        permission = await service.requestPermission();
      }
      if (permission != PermissionState.granted) {
        if (!mounted) return;
        setState(() {
          locationError = permission == PermissionState.permanentlyDenied
              ? 'Izin lokasi ditolak permanen. Buka pengaturan aplikasi lalu izinkan lokasi.'
              : 'Izin lokasi diperlukan untuk serah-terima aset.';
        });
        return;
      }
      final evidence = await service.currentEvidence();
      if (!mounted) return;
      setState(() => location = evidence);
    } catch (e) {
      if (!mounted) return;
      setState(() => locationError = apiErrorMessage(e));
    } finally {
      if (mounted) setState(() => locationLoading = false);
    }
  }

  Future<bool> _refreshLocationEvidence() async {
    try {
      final evidence = await ref
          .read(locationServiceProvider)
          .currentEvidence();
      if (!mounted) return false;
      setState(() => location = evidence);
      return true;
    } catch (e) {
      if (mounted) setState(() => error = apiErrorMessage(e));
      return false;
    }
  }

  Future<void> _startQrScan() async {
    if (step != 1) return;
    await _stopFaceDetection();
    await _closeFaceCamera();
    if (!mounted) return;
    setState(() => scanning = true);
  }

  Future<void> _closeFaceCamera() async {
    final controller = faceCamera;
    faceCamera = null;
    await controller?.dispose();
  }

  Future<void> startFaceCapture() async {
    if (step != 2) return;
    if (faceCamera != null || faceLoading) return;
    setState(() {
      scanning = false;
      faceLoading = true;
      faceStatus = 'Menyiapkan kamera...';
      error = null;
    });
    await _closeFaceCamera();
    if (!mounted) return;
    try {
      var permission = await AppPermissions.status(Permission.camera);
      if (permission != PermissionState.granted) {
        permission = await AppPermissions.request(Permission.camera);
      }
      if (permission != PermissionState.granted) {
        if (!mounted) return;
        setState(() {
          error = permission == PermissionState.permanentlyDenied
              ? 'Izin kamera ditolak permanen. Buka pengaturan aplikasi lalu izinkan kamera.'
              : 'Izin kamera diperlukan untuk verifikasi wajah.';
          faceStatus = null;
        });
        return;
      }

      final cameras = await availableCameras();
      if (cameras.isEmpty) {
        if (!mounted) return;
        setState(() {
          error = 'Kamera tidak ditemukan pada perangkat ini.';
          faceStatus = null;
        });
        return;
      }
      final selected = cameras.firstWhere(
        (item) => item.lensDirection == CameraLensDirection.front,
        orElse: () => cameras.first,
      );
      final controller = CameraController(
        selected,
        ResolutionPreset.medium,
        enableAudio: false,
        imageFormatGroup: cameraImageFormatGroup(),
      );
      await controller.initialize();
      if (!mounted) {
        await controller.dispose();
        return;
      }
      setState(() {
        faceCamera = controller;
        faceStatus = 'Mencari wajah...';
      });
      _faceDetector ??= FaceDetector(
        options: FaceDetectorOptions(
          enableTracking: true,
          performanceMode: FaceDetectorMode.accurate,
          minFaceSize: 0.15,
        ),
      );
      await _startFaceDetection();
    } catch (e) {
      if (!mounted) return;
      setState(() {
        error = apiErrorMessage(e);
        faceStatus = null;
      });
    } finally {
      if (mounted) setState(() => faceLoading = false);
    }
  }

  Future<void> retakeFace() async {
    await _stopFaceDetection();
    await _closeFaceCamera();
    if (!mounted) return;
    setState(() {
      faceFrame = null;
      faceDetected = false;
      faceYaw = null;
      _captureAttempts = 0;
      _faceGuidance.reset();
      faceStatus = null;
      error = null;
    });
    await startFaceCapture();
  }

  Future<void> _startFaceDetection() async {
    final controller = faceCamera;
    if (!mounted ||
        step != 2 ||
        controller == null ||
        !controller.value.isInitialized ||
        faceFrame != null ||
        capturingFace ||
        processingFrame ||
        _captureAttempts >= _maxAutoCaptureAttempts) {
      return;
    }
    await _stopFaceDetection();
    if (!mounted) return;
    _faceGuidance.reset();
    faceDetected = false;
    faceYaw = null;
    _frameCounter = 0;
    if (!controller.value.isStreamingImages) {
      await controller.startImageStream(_onFaceImage);
    }
  }

  Future<void> _stopFaceDetection() async {
    final controller = faceCamera;
    if (controller != null && controller.value.isStreamingImages) {
      await controller.stopImageStream();
    }
  }

  Future<void> _onFaceImage(CameraImage image) async {
    if (!mounted ||
        step != 2 ||
        processingFrame ||
        capturingFace ||
        faceFrame != null ||
        busy) {
      return;
    }
    _frameCounter += 1;
    if (_frameCounter % _frameSampling != 0) return;
    processingFrame = true;
    try {
      final detector = _faceDetector;
      final controller = faceCamera;
      if (detector == null || controller == null) return;
      final inputImage = inputImageFromCameraImage(image, controller);
      if (inputImage == null) return;
      final faces = await detector.processImage(inputImage);
      if (!mounted ||
          faceFrame != null ||
          busy ||
          !controller.value.isStreamingImages) {
        return;
      }
      final face = faces.isEmpty
          ? null
          : faces.reduce(
              (a, b) => a.boundingBox.width >= b.boundingBox.width ? a : b,
            );
      final detected = face != null;
      final yaw = face?.headEulerAngleY;
      final action = _faceGuidance.update(faceDetected: detected, yaw: yaw);
      if (detected != faceDetected || yaw != faceYaw) {
        setState(() {
          faceDetected = detected;
          faceYaw = yaw;
          faceStatus = detected
              ? 'Wajah terdeteksi, tahan sebentar.'
              : 'Mencari wajah...';
        });
      }
      if (action == FaceGuidanceAction.captureFront) {
        await _stopFaceDetection();
        unawaited(_captureDetectedFace());
      }
    } catch (_) {
      return;
    } finally {
      processingFrame = false;
    }
  }

  Future<void> _captureDetectedFace() async {
    final controller = faceCamera;
    if (controller == null || capturingFace) return;
    await _stopFaceDetection();
    setState(() {
      capturingFace = true;
      _captureAttempts += 1;
    });
    try {
      final frame = await ref.read(faceRepositoryProvider).capture(controller);
      if (!mounted) return;
      setState(() {
        faceFrame = frame;
        capturingFace = false;
        faceStatus = 'Foto wajah tersimpan.';
        error = null;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        capturingFace = false;
        faceStatus = 'Gagal mengambil wajah. Coba lagi.';
        error = apiErrorMessage(e);
      });
    } finally {
      if (mounted && capturingFace) {
        setState(() => capturingFace = false);
      }
    }
    if (mounted &&
        faceFrame == null &&
        _captureAttempts < _maxAutoCaptureAttempts) {
      unawaited(_startFaceDetection());
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Serah-terima')),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            VerificationStepHeader(current: step),
            const SizedBox(height: 20),
            switch (step) {
              0 => _locationStep(),
              1 => _qrStep(),
              2 => _faceStep(),
              _ => _timeStep(),
            },
            if (error != null) ...[
              const SizedBox(height: 12),
              Text(
                error!,
                style: TextStyle(color: Theme.of(context).colorScheme.error),
              ),
            ],
            const SizedBox(height: 16),
            _navigation(),
          ],
        ),
      ),
    );
  }

  Widget _locationStep() {
    return _StepCard(
      icon: Icons.my_location,
      title: 'Langkah 1 - Lokasi',
      description: 'Aktifkan GPS dan izinkan akses lokasi. Server akan memeriksa lokasi saat aset diserahkan.',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _StatusLine(
            ready: location != null,
            readyText: 'Lokasi berhasil diambil.',
            pendingText: 'Lokasi belum diambil.',
          ),
          if (location != null)
            Padding(
              padding: const EdgeInsets.only(top: 8),
              child: Text(
                'Akurasi GPS: ${location!.accuracy.toStringAsFixed(0)} m',
              ),
            ),
          if (location != null && !location!.preciseAccuracy)
            Padding(
              padding: const EdgeInsets.only(top: 8),
              child: OutlinedButton.icon(
                onPressed: AppPermissions.openSettings,
                icon: const Icon(Icons.settings),
                label: const Text('Aktifkan lokasi presisi'),
              ),
            ),
          if (locationError != null)
            Padding(
              padding: const EdgeInsets.only(top: 12),
              child: Text(
                locationError!,
                style: TextStyle(color: Theme.of(context).colorScheme.error),
              ),
            ),
          const SizedBox(height: 12),
          FilledButton.icon(
            onPressed: locationLoading ? null : _captureLocation,
            icon: locationLoading
                ? const SizedBox.square(
                    dimension: 18,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(Icons.location_on),
            label: Text(
              locationLoading
                  ? 'Mengambil lokasi...'
                  : location == null
                  ? 'Ambil lokasi'
                  : 'Perbarui lokasi',
            ),
          ),
        ],
      ),
    );
  }

  Widget _qrStep() {
    return _StepCard(
      icon: Icons.qr_code_scanner,
      title: 'Langkah 2 - QR aset',
      description: 'Pindai label statis pada aset. Token QR akan diverifikasi server terhadap peminjaman ini.',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _StatusLine(
            ready: assetToken.text.trim().isNotEmpty,
            readyText: 'Label aset berhasil dipindai.',
            pendingText: 'Label aset belum dipindai.',
          ),
          const SizedBox(height: 12),
          if (scanning)
            SizedBox(
              height: 220,
              child: MobileScanner(
                onDetect: (capture) {
                  final value = capture.barcodes.firstOrNull?.rawValue;
                  if (value != null && mounted) {
                    setState(() {
                      assetToken.text = value;
                      scanning = false;
                    });
                  }
                },
              ),
            )
          else
            OutlinedButton.icon(
              onPressed: busy ? null : _startQrScan,
              icon: const Icon(Icons.qr_code_scanner),
              label: const Text('Pindai label aset'),
            ),
          TextField(
            controller: assetToken,
            onChanged: (_) => setState(() {}),
            decoration: const InputDecoration(
              labelText: 'Hasil pindai QR aset',
            ),
          ),
        ],
      ),
    );
  }

  Widget _faceStep() {
    final faceController = faceCamera;
    final outline = Theme.of(context).colorScheme.outlineVariant;
    return _StepCard(
      icon: Icons.face,
      title: 'Langkah 3 - Verifikasi wajah',
      description: 'Minta peminjam menghadap kamera. Server mencocokkan fotonya dengan data wajah peminjam yang terdaftar.',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _StatusLine(
            ready: faceFrame != null,
            readyText: 'Foto wajah tersimpan.',
            pendingText: 'Foto wajah belum diambil.',
          ),
          const SizedBox(height: 12),
          if (faceFrame != null)
            Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    border: Border.all(color: outline),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Text(
                    faceStatus ?? 'Foto wajah tersimpan.',
                    textAlign: TextAlign.center,
                  ),
                ),
                const SizedBox(height: 8),
                OutlinedButton.icon(
                  onPressed: busy ? null : retakeFace,
                  icon: const Icon(Icons.refresh),
                  label: const Text('Ulangi wajah'),
                ),
              ],
            )
          else if (faceController != null && faceController.value.isInitialized)
            Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                SizedBox(
                  height: 220,
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(12),
                    child: CameraPreviewBox(
                      controller: faceController,
                      borderRadius: 0,
                    ),
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  faceStatus ?? 'Mencari wajah...',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: Theme.of(context).colorScheme.onSurfaceVariant,
                  ),
                ),
                const SizedBox(height: 8),
                OutlinedButton.icon(
                  onPressed: busy || faceLoading ? null : retakeFace,
                  icon: const Icon(Icons.refresh),
                  label: const Text('Ulangi wajah'),
                ),
              ],
            )
          else
            OutlinedButton.icon(
              onPressed: busy || faceLoading ? null : startFaceCapture,
              icon: faceLoading
                  ? const SizedBox.square(
                      dimension: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.face),
              label: Text(faceLoading ? 'Menyiapkan kamera...' : 'Ambil wajah'),
            ),
        ],
      ),
    );
  }

  Widget _timeStep() {
    final reviewedAt = timeReviewedAt ?? DateTime.now();
    return _StepCard(
      icon: Icons.schedule_rounded,
      title: 'Langkah 4 - Waktu',
      description: 'Server akan memeriksa apakah serah-terima dilakukan dalam rentang waktu yang diizinkan.',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const _StatusLine(
            ready: true,
            readyText:
                'Waktu serah-terima akan divalidasi server saat konfirmasi.',
            pendingText: '',
          ),
          const SizedBox(height: 10),
          Text(
            'Waktu perangkat: ${formatWibDateTime(reviewedAt.toUtc().toIso8601String())}',
          ),
          const Padding(
            padding: EdgeInsets.only(top: 12),
            child: _InfoBanner(
              icon: Icons.info_outline,
              message: 'Aplikasi tidak menentukan sendiri apakah waktu Anda valid. Tekan serahkan untuk membiarkan server memutuskan.',
            ),
          ),
        ],
      ),
    );
  }

  Widget _navigation() {
    final canContinue = switch (step) {
      0 => location != null,
      1 => assetToken.text.trim().isNotEmpty,
      2 => faceFrame != null,
      _ => true,
    };
    return Row(
      children: [
        if (step > 0)
          OutlinedButton(
            onPressed: busy ? null : _goBack,
            child: const Text('Kembali'),
          ),
        const Spacer(),
        if (step < 3)
          FilledButton(
            onPressed: canContinue && !busy ? _nextStep : null,
            child: const Text('Lanjut'),
          )
        else
          ElevatedButton(
            onPressed: busy ? null : submit,
            child: Text(busy ? 'Memproses...' : 'Verifikasi dan serahkan'),
          ),
      ],
    );
  }

  void _nextStep() {
    setState(() {
      step += 1;
      error = null;
      if (step == 3) timeReviewedAt = DateTime.now();
    });
    if (step == 3) unawaited(_refreshLocationEvidence());
  }

  void _goBack() {
    if (step == 2) {
      unawaited(_stopFaceDetection());
      unawaited(_closeFaceCamera());
      if (!mounted) return;
      setState(() {
        faceStatus = null;
        step = 1;
      });
      return;
    }
    setState(() => step -= 1);
  }
}

class _StepCard extends StatelessWidget {
  const _StepCard({
    required this.icon,
    required this.title,
    required this.description,
    required this.child,
  });

  final IconData icon;
  final String title;
  final String description;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(icon),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    title,
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            Text(description),
            const SizedBox(height: 16),
            child,
          ],
        ),
      ),
    );
  }
}

class _StatusLine extends StatelessWidget {
  const _StatusLine({
    required this.ready,
    required this.readyText,
    required this.pendingText,
  });

  final bool ready;
  final String readyText;
  final String pendingText;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Icon(
          ready ? Icons.check_circle : Icons.radio_button_unchecked,
          size: 18,
          color: ready ? Colors.green : Theme.of(context).colorScheme.outline,
        ),
        const SizedBox(width: 8),
        Expanded(child: Text(ready ? readyText : pendingText)),
      ],
    );
  }
}

class _InfoBanner extends StatelessWidget {
  const _InfoBanner({required this.icon, required this.message});

  final IconData icon;
  final String message;

  @override
  Widget build(BuildContext context) {
    final colorScheme = Theme.of(context).colorScheme;
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: colorScheme.surfaceContainerHighest,
        borderRadius: BorderRadius.circular(10),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 20),
          const SizedBox(width: 8),
          Expanded(child: Text(message)),
        ],
      ),
    );
  }
}

class ReturnPage extends ConsumerStatefulWidget {
  const ReturnPage({required this.loanId, super.key});
  final String loanId;
  @override
  ConsumerState<ReturnPage> createState() => _ReturnPageState();
}

class _ReturnPageState extends ConsumerState<ReturnPage> {
  final notes = TextEditingController();
  final assetToken = TextEditingController();
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
            assetToken: assetToken.text.trim().isEmpty
                ? null
                : assetToken.text.trim(),
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
                      assetToken.text = value;
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
            controller: assetToken,
            decoration: const InputDecoration(
              labelText: 'Hasil pindai QR aset (opsional)',
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
