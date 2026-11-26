import 'dart:async';
import 'dart:convert';
import 'dart:typed_data';

import 'package:camera/camera.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:google_mlkit_face_detection/google_mlkit_face_detection.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import 'package:permission_handler/permission_handler.dart';

import '../../../core/formatting/formatters.dart';
import '../../../core/network/api_error.dart';
import '../../../core/permissions/app_permissions.dart';
import '../../../core/verification/verification_steps.dart';
import '../../../core/widgets/camera_preview_box.dart';
import '../../bookings/presentation/booking_providers.dart';
import '../../face/data/face_guidance.dart';
import '../../face/data/face_repository.dart';
import '../data/check_in_repository.dart';
import '../data/location_service.dart';

enum _FaceCaptureKind { front, turn }

class CheckInPage extends ConsumerStatefulWidget {
  const CheckInPage({required this.bookingId, super.key});
  final String bookingId;

  @override
  ConsumerState<CheckInPage> createState() => _CheckInPageState();
}

class _CheckInPageState extends ConsumerState<CheckInPage> {
  static const _maxAutoCaptureAttempts = 2;
  static const _frameSampling = 3;

  int step = 0;
  LocationEvidence? location;
  String? qrToken;
  bool locationLoading = false;
  bool faceStatusLoading = false;
  bool cameraLoading = false;
  bool submitting = false;
  bool? accepted;
  String? message;
  String? locationError;
  String? faceError;
  bool faceEnabled = false;
  bool faceEnrolled = false;
  bool faceStatusReady = false;
  Map<String, dynamic>? challenge;
  CameraController? camera;
  Uint8List? frontFrame;
  Uint8List? faceFrame;
  FaceDetector? _faceDetector;
  final FaceGuidanceTracker _guidance = FaceGuidanceTracker();
  bool _faceDetected = false;
  double? _faceYaw;
  int _frameCounter = 0;
  bool _processingFrame = false;
  bool _capturingFrame = false;
  int _autoCaptureAttempts = 0;

  bool get faceRequired => faceEnabled;

  String get _detectionStatus {
    if (_capturingFrame) return 'Memproses foto...';
    if (!_faceDetected) return 'Mencari wajah...';
    return 'Wajah terdeteksi, pertahankan sebentar.';
  }

  @override
  void dispose() {
    unawaited(_stopFaceDetection());
    unawaited(_faceDetector?.close() ?? Future<void>.value());
    frontFrame = null;
    faceFrame = null;
    camera?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final colorScheme = Theme.of(context).colorScheme;
    final booking = ref
        .watch(bookingDetailProvider(widget.bookingId))
        .asData
        ?.value;
    final roomLocation = _roomLocation(booking);
    return Scaffold(
      appBar: AppBar(title: const Text('Konfirmasi booking')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          VerificationStepHeader(current: step),
          const SizedBox(height: 20),
          AnimatedSwitcher(
            duration: const Duration(milliseconds: 200),
            child: switch (step) {
              0 => _locationStep(roomLocation),
              1 => _qrStep(),
              2 => _faceStep(),
              _ => _timeStep(booking),
            },
          ),
          const SizedBox(height: 24),
          if (message != null) _resultCard(colorScheme),
          const SizedBox(height: 8),
          _navigation(),
        ],
      ),
    );
  }

  Map<String, dynamic>? _roomLocation(Map<String, dynamic>? booking) {
    final resource = booking?['resource'];
    if (resource is! Map) return null;
    final location = resource['location'];
    if (location is! Map) return null;
    return Map<String, dynamic>.from(location);
  }

  int? _radiusOf(Map<String, dynamic> location) {
    final value = location['radius'];
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '');
  }

  String _areaDescription(Map<String, dynamic> location) {
    final polygon = location['polygon'];
    if (polygon is List && polygon.length >= 3) {
      return 'Area ruangan: area bebas (${polygon.length} titik).';
    }
    final radius = _radiusOf(location);
    if (radius == null) return 'Area ruangan: radius belum diatur.';
    return 'Radius area ruangan: ${_formatMeters(radius)}.';
  }

  String _formatMeters(int meters) {
    if (meters < 1000) return '$meters m';
    final km = meters / 1000;
    final label = km == km.roundToDouble()
        ? km.toStringAsFixed(0)
        : km.toStringAsFixed(1);
    return '$label km';
  }

  double? _distanceToArea(Map<String, dynamic> roomLocation) {
    final current = location;
    if (current == null) return null;
    final latitude = double.tryParse(
      roomLocation['latitude']?.toString() ?? '',
    );
    final longitude = double.tryParse(
      roomLocation['longitude']?.toString() ?? '',
    );
    if (latitude == null || longitude == null) return null;
    return distanceBetweenMeters(
      fromLatitude: current.latitude,
      fromLongitude: current.longitude,
      toLatitude: latitude,
      toLongitude: longitude,
    );
  }

  Widget _locationStep(Map<String, dynamic>? roomLocation) {
    final evidence = location;
    final ready = evidence != null;
    final distance = roomLocation == null
        ? null
        : _distanceToArea(roomLocation);
    return _StepCard(
      key: const ValueKey('location-step'),
      icon: Icons.my_location,
      title: 'Langkah 1 - Lokasi',
      description: 'Aktifkan GPS dan izinkan akses lokasi. Server akan memeriksa apakah Anda berada di dalam area ruangan.',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (roomLocation != null)
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: Text(_areaDescription(roomLocation)),
            ),
          _StatusLine(
            ready: ready,
            readyText: 'Lokasi berhasil diambil.',
            pendingText: 'Lokasi belum diambil.',
          ),
          if (evidence != null)
            Padding(
              padding: const EdgeInsets.only(top: 8),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Akurasi GPS: ${evidence.accuracy.toStringAsFixed(0)} m',
                  ),
                  if (distance != null)
                    Padding(
                      padding: const EdgeInsets.only(top: 4),
                      child: Text(
                        'Perkiraan jarak ke pusat area: ${distance.toStringAsFixed(0)} m',
                      ),
                    ),
                ],
              ),
            ),
          if (evidence != null && !evidence.preciseAccuracy) ...[
            const Padding(
              padding: EdgeInsets.only(top: 8),
              child: _InfoBanner(
                icon: Icons.warning_amber_rounded,
                message: 'Izin lokasi presisi belum aktif. Aktifkan "Lokasi presisi" di pengaturan aplikasi agar akurasi GPS lebih baik.',
              ),
            ),
            OutlinedButton.icon(
              onPressed: AppPermissions.openSettings,
              icon: const Icon(Icons.settings),
              label: const Text('Buka pengaturan'),
            ),
          ],
          if (locationError != null)
            Padding(
              padding: const EdgeInsets.only(top: 12),
              child: _InlineError(message: locationError!),
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
                  : ready
                  ? 'Perbarui lokasi'
                  : 'Ambil lokasi',
            ),
          ),
        ],
      ),
    );
  }

  Widget _qrStep() {
    return _StepCard(
      key: const ValueKey('qr-step'),
      icon: Icons.qr_code_scanner,
      title: 'Langkah 2 - QR ruangan',
      description: 'Pindai QR yang ditampilkan di dalam ruangan. Token QR berumur pendek dan hanya berlaku untuk booking ini.',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _StatusLine(
            ready: qrToken != null,
            readyText: 'QR berhasil dipindai.',
            pendingText: 'QR belum dipindai.',
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
            )
          else
            OutlinedButton.icon(
              onPressed: () => setState(() => qrToken = null),
              icon: const Icon(Icons.refresh),
              label: const Text('Pindai ulang'),
            ),
        ],
      ),
    );
  }

  Widget _faceStep() {
    return _StepCard(
      key: const ValueKey('face-step'),
      icon: Icons.face_retouching_natural,
      title: 'Langkah 3 - Verifikasi wajah',
      description: 'Foto depan diambil otomatis saat wajah terdeteksi, lalu foto toleh diambil saat kepala berputar. Setelah kedua foto selesai, lanjut ke langkah waktu.',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (faceStatusLoading || cameraLoading)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 12),
              child: LinearProgressIndicator(),
            ),
          if (!faceStatusLoading && !faceEnabled)
            const _InfoBanner(
              icon: Icons.info_outline,
              message: 'Verifikasi wajah sedang tidak diwajibkan oleh server. Anda dapat langsung mengonfirmasi.',
            ),
          if (faceError != null) ...[
            _InlineError(message: faceError!),
            const SizedBox(height: 8),
            OutlinedButton.icon(
              onPressed: faceStatusLoading ? null : _prepareFaceStep,
              icon: const Icon(Icons.refresh),
              label: const Text('Coba lagi'),
            ),
          ],
          if (faceEnabled &&
              faceEnrolled &&
              accepted == false &&
              message != null)
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: OutlinedButton.icon(
                onPressed: submitting ? null : _retryFaceCapture,
                icon: const Icon(Icons.face_retouching_natural),
                label: const Text('Ulangi verifikasi wajah'),
              ),
            ),
          if (faceEnabled && !faceEnrolled && !faceStatusLoading)
            _EnrollmentPrompt(onEnroll: _openEnrollment),
          if (faceEnabled && faceEnrolled) ...[
            if (challenge != null)
              _InfoBanner(
                icon: Icons.remove_red_eye_outlined,
                message:
                    'Gerakan diminta: ${faceChallengeInstruction(challenge!['challenge_type']?.toString())}.',
              ),
            if (camera?.value.isInitialized == true)
              Padding(
                padding: const EdgeInsets.only(top: 12),
                child: Semantics(
                  liveRegion: true,
                  child: Text(
                    frontFrame == null
                        ? 'Hadapkan wajah ke kamera. $_detectionStatus'
                        : faceFrame == null
                        ? 'Foto depan tersimpan. Sekarang tolehkan kepala ke kiri atau kanan. $_detectionStatus'
                        : 'Foto depan dan tolehan tersimpan. Lanjut ke langkah waktu.',
                  ),
                ),
              ),
            const SizedBox(height: 12),
            if (camera?.value.isInitialized == true)
              SizedBox(
                height: 300,
                width: double.infinity,
                child: CameraPreviewBox(controller: camera!, borderRadius: 12),
              ),
            const SizedBox(height: 12),
            if (camera?.value.isInitialized == true && faceFrame == null)
              Row(
                children: [
                  Icon(
                    frontFrame == null
                        ? Icons.face_retouching_natural
                        : Icons.turn_right,
                    color: Theme.of(context).colorScheme.primary,
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      frontFrame == null
                          ? 'Menunggu wajah menghadap kamera...'
                          : 'Menunggu kepala menoleh...',
                    ),
                  ),
                  if (submitting)
                    const SizedBox.square(
                      dimension: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    ),
                ],
              ),
          ],
        ],
      ),
    );
  }

  Widget _timeStep(Map<String, dynamic>? booking) {
    final startsAt = booking?['starts_at']?.toString();
    final endsAt = booking?['ends_at']?.toString();
    return _StepCard(
      key: const ValueKey('time-step'),
      icon: Icons.schedule_rounded,
      title: 'Langkah 4 - Waktu',
      description: 'Server akan memeriksa apakah check-in dilakukan dalam rentang waktu yang diizinkan untuk booking ini.',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _StatusLine(
            ready: booking != null,
            readyText: 'Detail waktu booking tersedia.',
            pendingText: 'Detail booking belum tersedia.',
          ),
          if (startsAt != null && endsAt != null) ...[
            const SizedBox(height: 10),
            Text('Mulai: ${formatWibDateTime(startsAt)}'),
            const SizedBox(height: 4),
            Text('Selesai: ${formatWibDateTime(endsAt)}'),
          ],
          const Padding(
            padding: EdgeInsets.only(top: 12),
            child: _InfoBanner(
              icon: Icons.info_outline,
              message: 'Aplikasi tidak menentukan sendiri apakah waktu Anda valid. Tekan konfirmasi untuk membiarkan server memutuskan.',
            ),
          ),
        ],
      ),
    );
  }

  Widget _navigation() {
    final canContinue = switch (step) {
      0 => location != null,
      1 => qrToken != null,
      2 =>
        faceStatusReady &&
            (!faceRequired ||
                (faceEnrolled && frontFrame != null && faceFrame != null)),
      _ => true,
    };

    return Row(
      children: [
        if (step > 0)
          OutlinedButton(
            onPressed: submitting ? null : _goBack,
            child: const Text('Kembali'),
          ),
        const Spacer(),
        if (step < 3)
          FilledButton(
            onPressed: canContinue && !submitting ? _nextStep : null,
            child: const Text('Lanjut'),
          )
        else
          FilledButton.icon(
            onPressed: canContinue && !submitting ? () => _submit() : null,
            icon: submitting
                ? const SizedBox.square(
                    dimension: 18,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(Icons.verified_user),
            label: Text(submitting ? 'Mengirim...' : 'Konfirmasi check-in'),
          ),
      ],
    );
  }

  Widget _resultCard(ColorScheme colorScheme) {
    final isAccepted = accepted ?? false;
    return Semantics(
      liveRegion: true,
      child: Container(
        margin: const EdgeInsets.only(top: 20),
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: isAccepted
              ? colorScheme.primaryContainer
              : colorScheme.errorContainer,
          borderRadius: BorderRadius.circular(12),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(
              isAccepted ? Icons.check_circle : Icons.error,
              color: isAccepted
                  ? colorScheme.onPrimaryContainer
                  : colorScheme.onErrorContainer,
            ),
            const SizedBox(width: 12),
            Expanded(child: Text(message!)),
          ],
        ),
      ),
    );
  }

  void _nextStep() {
    setState(() {
      step += 1;
      message = null;
    });
    if (step == 2) unawaited(_prepareFaceStep());
  }

  void _goBack() {
    if (step == 2 || step == 3) {
      unawaited(_stopFaceDetection());
      final controller = camera;
      final returningToFace = step == 3;
      setState(() {
        camera = null;
        frontFrame = null;
        faceFrame = null;
        challenge = null;
        faceStatusReady = false;
        step = returningToFace ? 2 : 1;
      });
      controller?.dispose();
      if (returningToFace) unawaited(_prepareFaceStep());
      return;
    }
    setState(() => step -= 1);
  }

  Future<void> _captureLocation() async {
    setState(() {
      locationLoading = true;
      locationError = null;
      location = null;
    });
    try {
      final service = ref.read(locationServiceProvider);
      final permission = await service.permissionState();
      if (permission != PermissionState.granted) {
        final requested = await service.requestPermission();
        if (requested != PermissionState.granted) {
          if (!mounted) return;
          setState(() {
            locationError = requested == PermissionState.permanentlyDenied
                ? 'Izin lokasi ditolak permanen. Buka pengaturan aplikasi lalu izinkan lokasi.'
                : 'Izin lokasi diperlukan untuk konfirmasi booking.';
          });
          return;
        }
      }
      final evidence = await service.currentEvidence();
      if (!mounted) return;
      setState(() => location = evidence);
    } catch (error) {
      if (!mounted) return;
      setState(() => locationError = apiErrorMessage(error));
    } finally {
      if (mounted) setState(() => locationLoading = false);
    }
  }

  Future<void> _prepareFaceStep() async {
    await _stopFaceDetection();
    setState(() {
      faceStatusLoading = true;
      faceStatusReady = false;
      faceError = null;
      frontFrame = null;
      faceFrame = null;
      _autoCaptureAttempts = 0;
    });
    try {
      final faceRepository = ref.read(faceRepositoryProvider);
      final status = await faceRepository.status();
      if (!mounted || step != 2) return;
      final booking = ref
          .read(bookingDetailProvider(widget.bookingId))
          .asData
          ?.value;
      final resource = booking?['resource'];
      final enabled = faceVerificationRequired(
        status,
        resource is Map ? resource['type'] : null,
      );
      final enrolled = status['enrolled'] == true;
      setState(() {
        faceEnabled = enabled;
        faceEnrolled = enrolled;
        faceStatusReady = true;
      });
      if (enabled && enrolled) {
        final issued = await faceRepository.challenge();
        if (!mounted || step != 2) return;
        setState(() => challenge = issued);
        await _initCamera();
      }
    } catch (error) {
      if (!mounted) return;
      setState(() => faceError = apiErrorMessage(error));
    } finally {
      if (mounted) setState(() => faceStatusLoading = false);
    }
  }

  Future<void> _initCamera() async {
    if (step != 2 || camera != null || cameraLoading) return;
    setState(() => cameraLoading = true);
    try {
      var permission = await AppPermissions.status(Permission.camera);
      if (permission != PermissionState.granted) {
        permission = await AppPermissions.request(Permission.camera);
        if (permission != PermissionState.granted) {
          if (!mounted) return;
          setState(() {
            faceError = permission == PermissionState.permanentlyDenied
                ? 'Izin kamera ditolak permanen. Buka pengaturan aplikasi lalu izinkan kamera.'
                : 'Izin kamera diperlukan untuk verifikasi wajah.';
          });
          return;
        }
      }
      final cameras = await availableCameras();
      if (cameras.isEmpty) {
        if (!mounted) return;
        setState(
          () => faceError = 'Kamera tidak ditemukan pada perangkat ini.',
        );
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
      if (!mounted || step != 2) {
        await controller.dispose();
        return;
      }
      setState(() => camera = controller);
      _faceDetector ??= FaceDetector(
        options: FaceDetectorOptions(
          enableTracking: true,
          performanceMode: FaceDetectorMode.accurate,
          minFaceSize: 0.15,
        ),
      );
      await _startFaceDetection();
    } catch (error) {
      if (!mounted) return;
      setState(() => faceError = apiErrorMessage(error));
    } finally {
      if (mounted) setState(() => cameraLoading = false);
    }
  }

  Future<void> _renewChallenge() async {
    try {
      final issued = await ref.read(faceRepositoryProvider).challenge();
      if (!mounted) return;
      setState(() {
        challenge = issued;
        faceError = null;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() => faceError = apiErrorMessage(error));
    }
  }

  Future<void> _retryFaceCapture() async {
    await _stopFaceDetection();
    setState(() {
      frontFrame = null;
      faceFrame = null;
      faceError = null;
      message = null;
      accepted = null;
      _autoCaptureAttempts = 0;
      _faceDetected = false;
      _faceYaw = null;
      _guidance.reset();
    });
    await _renewChallenge();
    unawaited(_startFaceDetection());
  }

  Future<void> _startFaceDetection({bool resetGuidance = true}) async {
    final controller = camera;
    if (step != 2 ||
        !faceEnabled ||
        !faceEnrolled ||
        controller == null ||
        !controller.value.isInitialized ||
        faceFrame != null ||
        submitting ||
        _capturingFrame ||
        _autoCaptureAttempts >= _maxAutoCaptureAttempts) {
      return;
    }
    await _stopFaceDetection();
    if (resetGuidance) {
      _guidance.reset();
      _faceDetected = false;
      _faceYaw = null;
    }
    _frameCounter = 0;
    _processingFrame = false;
    if (!controller.value.isStreamingImages) {
      await controller.startImageStream(_onCameraImage);
    }
  }

  Future<void> _stopFaceDetection() async {
    final controller = camera;
    if (controller != null && controller.value.isStreamingImages) {
      await controller.stopImageStream();
    }
  }

  Future<void> _onCameraImage(CameraImage image) async {
    if (!mounted ||
        step != 2 ||
        _processingFrame ||
        _capturingFrame ||
        faceFrame != null ||
        submitting) {
      return;
    }
    _frameCounter += 1;
    if (_frameCounter % _frameSampling != 0) return;
    _processingFrame = true;
    try {
      final detector = _faceDetector;
      final controller = camera;
      if (detector == null || controller == null) return;
      final inputImage = inputImageFromCameraImage(image, controller);
      if (inputImage == null) return;
      final faces = await detector.processImage(inputImage);
      if (!mounted ||
          step != 2 ||
          faceFrame != null ||
          submitting ||
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
      final action = _guidance.update(faceDetected: detected, yaw: yaw);
      if (detected != _faceDetected || yaw != _faceYaw) {
        setState(() {
          _faceDetected = detected;
          _faceYaw = yaw;
        });
      }
      if (action == FaceGuidanceAction.captureFront ||
          action == FaceGuidanceAction.captureTurn) {
        await _stopFaceDetection();
        unawaited(
          _captureFace(
            kind: action == FaceGuidanceAction.captureFront
                ? _FaceCaptureKind.front
                : _FaceCaptureKind.turn,
          ),
        );
      }
    } catch (_) {
      return;
    } finally {
      _processingFrame = false;
    }
  }

  Future<void> _captureFace({required _FaceCaptureKind kind}) async {
    final controller = camera;
    if (controller == null || _capturingFrame) return;
    await _stopFaceDetection();
    setState(() => _capturingFrame = true);
    if (kind == _FaceCaptureKind.front) _autoCaptureAttempts += 1;
    var restartResetGuidance = true;
    try {
      final frame = await ref.read(faceRepositoryProvider).capture(controller);
      if (!mounted) return;
      if (kind == _FaceCaptureKind.front) {
        setState(() {
          frontFrame = frame;
          faceError = null;
          message = null;
          accepted = null;
          _capturingFrame = false;
        });
        _guidance.markFrontCaptured();
        restartResetGuidance = false;
      } else {
        setState(() {
          faceFrame = frame;
          faceError = null;
          message = null;
          accepted = null;
          _capturingFrame = false;
        });
        await _advanceFromFaceCapture();
        return;
      }
    } catch (error) {
      if (!mounted) return;
      setState(() {
        faceError = apiErrorMessage(error);
        _capturingFrame = false;
      });
    } finally {
      if (mounted && _capturingFrame) {
        setState(() => _capturingFrame = false);
      }
    }
    if (mounted &&
        faceFrame == null &&
        _autoCaptureAttempts < _maxAutoCaptureAttempts) {
      unawaited(_startFaceDetection(resetGuidance: restartResetGuidance));
    }
  }

  Future<void> _advanceFromFaceCapture() async {
    await _stopFaceDetection();
    if (!mounted) return;
    setState(() {
      step = 3;
      message = null;
    });
  }

  Future<void> _openEnrollment() async {
    await _stopFaceDetection();
    if (!mounted) return;
    final router = GoRouter.of(context);
    await router.push('/face/enrollment');
    if (mounted) await _prepareFaceStep();
  }

  Future<void> _submit() async {
    if (submitting ||
        step != 3 ||
        location == null ||
        qrToken == null ||
        !faceStatusReady) {
      return;
    }
    await _stopFaceDetection();
    if (faceRequired && !faceEnrolled) {
      setState(() {
        step = 2;
        accepted = false;
        message = 'Wajah belum terdaftar. Daftarkan wajah terlebih dahulu.';
      });
      return;
    }
    if (faceRequired && (frontFrame == null || faceFrame == null)) {
      setState(() {
        step = 2;
        accepted = false;
        message = 'Foto depan dan tolehan belum lengkap.';
      });
      return;
    }

    setState(() {
      submitting = true;
      message = null;
    });

    final challengeType = challenge?['challenge_type']?.toString();
    var retryFace = false;
    try {
      final result = await ref
          .read(checkInRepositoryProvider)
          .checkIn(
            bookingId: widget.bookingId,
            qrToken: qrToken!,
            location: location!,
            faceImageBase64: faceFrame == null
                ? null
                : base64Encode(faceFrame!),
            faceFrontImageBase64: frontFrame == null
                ? null
                : base64Encode(frontFrame!),
            faceChallenge: challengeType,
            faceChallengeResponse: challengeType == null
                ? null
                : {'action': challengeType},
          );
      final state =
          result['result']?.toString() ?? result['status']?.toString();
      final isAccepted = state == 'accepted';
      if (!mounted) return;
      final rejectionReasons = result['rejection_reasons'];
      final hasFaceReason =
          rejectionReasons is List &&
          rejectionReasons.any(
            (reason) => reason.toString().toLowerCase().contains('face'),
          );
      setState(() {
        accepted = isAccepted;
        message = checkInDecisionMessage(result);
        if (!isAccepted) {
          frontFrame = null;
          faceFrame = null;
        }
      });

      if (isAccepted) {
        ref.invalidate(bookingsProvider);
        ref.invalidate(bookingDetailProvider(widget.bookingId));
        context.pushReplacement(
          '/bookings/${widget.bookingId}',
          extra: {'just_checked_in': true},
        );
        return;
      }

      retryFace = faceEnabled && hasFaceReason;
    } catch (error) {
      if (!mounted) return;
      setState(() {
        accepted = false;
        message = apiErrorMessage(error);
      });
    } finally {
      if (mounted) {
        setState(() => submitting = false);
        if (retryFace) {
          setState(() => step = 2);
          unawaited(_prepareFaceStep());
        }
      }
    }
  }
}

class _StepCard extends StatelessWidget {
  const _StepCard({
    required super.key,
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

class _EnrollmentPrompt extends StatelessWidget {
  const _EnrollmentPrompt({required this.onEnroll});

  final VoidCallback onEnroll;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const _InfoBanner(
          icon: Icons.warning_amber_rounded,
          message: 'Wajah Anda belum terdaftar. Daftarkan wajah terlebih dahulu sebelum konfirmasi booking.',
        ),
        const SizedBox(height: 12),
        OutlinedButton.icon(
          onPressed: onEnroll,
          icon: const Icon(Icons.face),
          label: const Text('Daftar wajah sekarang'),
        ),
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

class _InlineError extends StatelessWidget {
  const _InlineError({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return Text(
      message,
      style: TextStyle(color: Theme.of(context).colorScheme.error),
    );
  }
}
