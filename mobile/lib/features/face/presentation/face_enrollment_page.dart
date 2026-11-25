import 'dart:async';
import 'dart:typed_data';

import 'package:camera/camera.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_mlkit_face_detection/google_mlkit_face_detection.dart';
import 'package:permission_handler/permission_handler.dart';

import '../../../core/network/api_error.dart';
import '../../../core/permissions/app_permissions.dart';
import '../../../core/widgets/camera_preview_box.dart';
import '../data/face_guidance.dart';
import '../data/face_repository.dart';

class FaceEnrollmentPage extends ConsumerStatefulWidget {
  const FaceEnrollmentPage({super.key});

  @override
  ConsumerState<FaceEnrollmentPage> createState() => _FaceEnrollmentPageState();
}

class _FaceEnrollmentPageState extends ConsumerState<FaceEnrollmentPage> {
  static const _frameSampling = 3;

  CameraController? camera;
  bool consent = false;
  bool initializing = true;
  bool capturing = false;
  bool enrolling = false;
  bool alreadyEnrolled = false;
  bool canOpenSettings = false;
  final List<Uint8List> frames = [];
  final List<String> poses = [];
  String? status;
  String? error;

  FaceDetector? _faceDetector;
  final FaceEnrollmentGuidanceTracker _guidance =
      FaceEnrollmentGuidanceTracker();
  bool _faceDetected = false;
  double? _faceYaw;
  int _frameCounter = 0;
  bool _processingFrame = false;

  bool get _busy => capturing || enrolling;

  int get _capturedCount => _guidance.capturedCount;

  @override
  void initState() {
    super.initState();
    _initCamera();
  }

  @override
  void dispose() {
    unawaited(_stopDetection());
    unawaited(_faceDetector?.close() ?? Future<void>.value());
    frames.clear();
    poses.clear();
    camera?.dispose();
    super.dispose();
  }

  Future<void> _initCamera() async {
    await _stopDetection();
    final previous = camera;
    camera = null;
    await previous?.dispose();

    if (mounted) {
      setState(() {
        initializing = true;
        error = null;
        canOpenSettings = false;
      });
    }

    try {
      var permission = await AppPermissions.status(Permission.camera);
      if (permission != PermissionState.granted) {
        permission = await AppPermissions.request(Permission.camera);
      }
      if (permission != PermissionState.granted) {
        if (!mounted) return;
        setState(() {
          initializing = false;
          canOpenSettings = permission == PermissionState.permanentlyDenied;
          error = canOpenSettings
              ? 'Izin kamera ditolak permanen. Buka pengaturan aplikasi lalu izinkan kamera.'
              : 'Izin kamera diperlukan untuk mendaftarkan wajah.';
        });
        return;
      }

      final cameras = await availableCameras();
      if (cameras.isEmpty) {
        if (!mounted) return;
        setState(() {
          initializing = false;
          error = 'Kamera tidak ditemukan pada perangkat ini.';
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
        camera = controller;
        initializing = false;
      });
      await _loadStatus();
      if (consent) unawaited(_startDetection());
    } catch (initError) {
      if (!mounted) return;
      setState(() {
        initializing = false;
        error = apiErrorMessage(initError);
      });
    }
  }

  Future<void> _loadStatus() async {
    try {
      final result = await ref.read(faceRepositoryProvider).status();
      if (!mounted) return;
      setState(() => alreadyEnrolled = result['enrolled'] == true);
    } catch (_) {
      // Status is only used for the label; enrollment still works without it.
    }
  }

  Future<void> _toggleConsent(bool value) async {
    if (value) {
      setState(() => consent = true);
      await _startDetection();
      return;
    }

    await _stopDetection();
    if (!mounted) return;
    setState(() {
      consent = false;
      _clearSamples();
      status = null;
      error = null;
    });
  }

  Future<void> _startDetection() async {
    final controller = camera;
    if (!consent ||
        _busy ||
        _guidance.complete ||
        controller == null ||
        !controller.value.isInitialized ||
        controller.value.isStreamingImages) {
      return;
    }

    _faceDetector ??= FaceDetector(
      options: FaceDetectorOptions(
        enableTracking: true,
        performanceMode: FaceDetectorMode.accurate,
        minFaceSize: 0.15,
      ),
    );
    _frameCounter = 0;
    _processingFrame = false;
    try {
      await controller.startImageStream(_onCameraImage);
    } catch (detectionError) {
      if (!mounted) return;
      setState(() => error = apiErrorMessage(detectionError));
    }
  }

  Future<void> _stopDetection() async {
    final controller = camera;
    if (controller != null && controller.value.isStreamingImages) {
      await controller.stopImageStream();
    }
  }

  Future<void> _onCameraImage(CameraImage image) async {
    if (!mounted ||
        !consent ||
        _busy ||
        _processingFrame ||
        _guidance.complete) {
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
          !consent ||
          _busy ||
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
      if (action != FaceEnrollmentAction.none) {
        await _stopDetection();
        unawaited(_captureSample(action: action, yaw: yaw));
      }
    } catch (_) {
      return;
    } finally {
      _processingFrame = false;
    }
  }

  Future<void> _captureSample({
    required FaceEnrollmentAction action,
    required double? yaw,
  }) async {
    final controller = camera;
    if (controller == null || _busy || !consent) return;

    final isFront = action == FaceEnrollmentAction.captureFront;
    final pose = isFront
        ? 'front'
        : (yaw ?? 0) < 0
        ? 'turn_left'
        : 'turn_right';

    setState(() {
      capturing = true;
      error = null;
      status = isFront
          ? 'Mengambil sampel hadap lurus...'
          : 'Mengambil sampel tolehan...';
    });

    try {
      final frame = await ref.read(faceRepositoryProvider).capture(controller);
      if (!mounted) return;

      if (isFront) {
        _guidance.markFrontCaptured();
      } else {
        _guidance.markTurnCaptured(yaw ?? 0);
      }

      setState(() {
        frames.add(frame);
        poses.add(pose);
        capturing = false;
        status = _guidance.complete
            ? 'Semua sampel terkumpul. Menyimpan data wajah...'
            : null;
      });

      if (_guidance.complete) {
        await _enroll();
      } else {
        unawaited(_startDetection());
      }
    } catch (captureError) {
      if (!mounted) return;
      setState(() {
        capturing = false;
        status = null;
        error = apiErrorMessage(captureError);
      });
      if (consent) unawaited(_startDetection());
    }
  }

  void _clearSamples() {
    _guidance.reset();
    frames.clear();
    poses.clear();
    _faceDetected = false;
    _faceYaw = null;
  }

  Future<void> _retry() async {
    await _stopDetection();
    if (!mounted) return;
    setState(() {
      _clearSamples();
      status = null;
      error = null;
    });
    if (consent) await _startDetection();
  }

  Future<void> _enroll() async {
    if (enrolling || frames.isEmpty) return;

    await _stopDetection();
    setState(() {
      enrolling = true;
      error = null;
      status = 'Menyimpan data wajah...';
    });

    try {
      await ref.read(faceRepositoryProvider).enroll(frames, poses);

      if (!mounted) return;
      setState(() {
        enrolling = false;
        alreadyEnrolled = true;
        status = 'Wajah berhasil didaftarkan.';
      });

      await showDialog<void>(
        context: context,
        barrierDismissible: false,
        builder: (dialogContext) => AlertDialog(
          icon: const Icon(Icons.check_circle, color: Colors.green, size: 40),
          title: const Text('Pendaftaran wajah berhasil'),
          content: const Text(
            'Wajah Anda sudah tersimpan. Sekarang Anda dapat membuat booking ruangan.',
          ),
          actions: [
            FilledButton(
              onPressed: () => Navigator.of(dialogContext).pop(),
              child: const Text('Lanjut'),
            ),
          ],
        ),
      );

      if (mounted) Navigator.of(context).pop(true);
    } catch (enrollError) {
      if (!mounted) return;
      setState(() {
        enrolling = false;
        status = null;
        error = apiErrorMessage(enrollError);
        _clearSamples();
      });
    }
  }

  Future<void> _delete() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Hapus data wajah?'),
        content: const Text(
          'Data wajah akan dihapus dan Anda perlu mendaftar ulang sebelum booking ruangan.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(false),
            child: const Text('Batal'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(dialogContext).pop(true),
            child: const Text('Hapus'),
          ),
        ],
      ),
    );
    if (confirmed != true) return;

    await _stopDetection();
    try {
      await ref.read(faceRepositoryProvider).delete();
      if (!mounted) return;
      setState(() {
        alreadyEnrolled = false;
        consent = false;
        _clearSamples();
        status = 'Data wajah dihapus.';
      });
    } catch (deleteError) {
      if (!mounted) return;
      setState(() => error = apiErrorMessage(deleteError));
    }
  }

  String get _stepTitle {
    if (enrolling) return 'Menyimpan data wajah...';
    if (capturing) return 'Mengambil sampel...';
    final step = (_capturedCount + 1).clamp(
      1,
      FaceEnrollmentGuidanceTracker.requiredSamples,
    );
    return 'Langkah $step dari ${FaceEnrollmentGuidanceTracker.requiredSamples}';
  }

  String get _poseTitle => switch (_capturedCount) {
    0 => 'Hadap lurus',
    1 => 'Toleh sisi pertama',
    _ => 'Toleh sisi sebaliknya',
  };

  String get _instruction {
    if (enrolling) return 'Menyimpan data wajah...';
    if (capturing) return 'Tahan posisi, foto sedang diambil.';
    if (_guidance.complete) return 'Semua sampel sudah diambil.';
    if (!consent) {
      return 'Centang persetujuan untuk memulai pengambilan foto otomatis.';
    }
    return switch (_capturedCount) {
      0 => 'Hadapkan wajah lurus ke kamera, lalu tahan sampai foto diambil otomatis.',
      1 => 'Tolehkan kepala ke salah satu sisi (kiri atau kanan), lalu tahan sampai foto diambil otomatis.',
      _ => 'Sekarang tolehkan kepala ke sisi sebaliknya, lalu tahan sampai foto diambil otomatis.',
    };
  }

  String get _detectionStatus {
    if (_guidance.complete) return 'Sampel lengkap.';
    if (!_faceDetected) return 'Mencari wajah...';
    return 'Wajah terdeteksi, pertahankan posisi.';
  }

  @override
  Widget build(BuildContext context) {
    if (initializing) {
      return Scaffold(
        appBar: AppBar(title: const Text('Daftar Wajah')),
        body: const Center(
          child: CircularProgressIndicator(semanticsLabel: 'Menyiapkan kamera'),
        ),
      );
    }

    if (camera == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Daftar Wajah')),
        body: Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Icon(Icons.no_photography_outlined, size: 56),
                const SizedBox(height: 16),
                Text(
                  error ?? 'Kamera tidak tersedia.',
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: 16),
                if (canOpenSettings)
                  FilledButton(
                    onPressed: AppPermissions.openSettings,
                    child: const Text('Buka pengaturan'),
                  ),
                const SizedBox(height: 8),
                OutlinedButton(
                  onPressed: _initCamera,
                  child: const Text('Coba lagi'),
                ),
              ],
            ),
          ),
        ),
      );
    }

    return Scaffold(
      backgroundColor: Colors.black,
      extendBodyBehindAppBar: true,
      appBar: AppBar(
        backgroundColor: Colors.black.withValues(alpha: 0.35),
        foregroundColor: Colors.white,
        elevation: 0,
        title: const Text('Daftar Wajah'),
      ),
      body: Stack(
        fit: StackFit.expand,
        children: [
          CameraPreviewBox(controller: camera!, borderRadius: 0),
          const IgnorePointer(child: CustomPaint(painter: _FaceGuidePainter())),
          SafeArea(
            child: Column(
              children: [
                const SizedBox(height: 64),
                _instructionCard(),
                const Spacer(),
                _controls(),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _instructionCard() {
    final showDetection =
        consent && !_busy && !_guidance.complete && camera != null;

    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16),
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      decoration: BoxDecoration(
        color: Colors.black.withValues(alpha: 0.55),
        borderRadius: BorderRadius.circular(16),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(
            _capturedCount == 0
                ? Icons.face_retouching_natural
                : Icons.rotate_right,
            color: Colors.white,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _guidance.complete || enrolling
                      ? _stepTitle
                      : '$_stepTitle - $_poseTitle',
                  style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.bold,
                    height: 1.35,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  _instruction,
                  style: const TextStyle(color: Colors.white, height: 1.35),
                ),
                if (showDetection) ...[
                  const SizedBox(height: 4),
                  Text(
                    _detectionStatus,
                    style: const TextStyle(
                      color: Colors.white70,
                      fontSize: 12,
                      height: 1.35,
                    ),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _controls() {
    final scheme = Theme.of(context).colorScheme;
    final progress =
        (_capturedCount / FaceEnrollmentGuidanceTracker.requiredSamples).clamp(
          0.0,
          1.0,
        );

    return Container(
      margin: const EdgeInsets.all(16),
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
      decoration: BoxDecoration(
        color: Colors.black.withValues(alpha: 0.72),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          CheckboxListTile(
            value: consent,
            onChanged: _busy ? null : (value) => _toggleConsent(value ?? false),
            controlAffinity: ListTileControlAffinity.leading,
            contentPadding: EdgeInsets.zero,
            dense: true,
            activeColor: scheme.primary,
            checkColor: scheme.onPrimary,
            side: const BorderSide(color: Colors.white70, width: 1.5),
            title: const Text(
              'Saya menyetujui penggunaan data wajah untuk verifikasi check-in.',
              style: TextStyle(color: Colors.white, fontSize: 13),
            ),
          ),
          if (status != null) ...[
            const SizedBox(height: 8),
            Text(
              status!,
              style: const TextStyle(color: Colors.greenAccent, fontSize: 13),
            ),
          ],
          if (error != null) ...[
            const SizedBox(height: 8),
            Text(
              error!,
              style: const TextStyle(color: Colors.redAccent, fontSize: 13),
            ),
          ],
          if (_capturedCount > 0 || enrolling) ...[
            const SizedBox(height: 12),
            LinearProgressIndicator(value: progress),
            const SizedBox(height: 6),
            Text(
              'Sampel $_capturedCount dari ${FaceEnrollmentGuidanceTracker.requiredSamples}',
              style: const TextStyle(color: Colors.white70, fontSize: 12),
            ),
          ],
          const SizedBox(height: 12),
          Text(
            consent
                ? 'Foto diambil otomatis saat wajah dan gerakan terdeteksi.'
                : 'Pengambilan foto otomatis dimulai setelah Anda menyetujui.',
            style: const TextStyle(color: Colors.white70, fontSize: 12),
          ),
          if (!_busy && error != null && _capturedCount == 0) ...[
            const SizedBox(height: 8),
            FilledButton.icon(
              onPressed: _retry,
              icon: const Icon(Icons.refresh),
              label: const Text('Coba lagi'),
            ),
          ],
          if (!_busy && _capturedCount > 0)
            TextButton(
              onPressed: _retry,
              child: const Text(
                'Ulangi dari awal',
                style: TextStyle(color: Colors.white70),
              ),
            ),
          if (alreadyEnrolled && !_busy && _capturedCount == 0)
            TextButton(
              onPressed: _delete,
              child: const Text(
                'Hapus data wajah',
                style: TextStyle(color: Colors.white70),
              ),
            ),
        ],
      ),
    );
  }
}

class _FaceGuidePainter extends CustomPainter {
  const _FaceGuidePainter();

  @override
  void paint(Canvas canvas, Size size) {
    final oval = Rect.fromCenter(
      center: Offset(size.width / 2, size.height * 0.40),
      width: size.width * 0.68,
      height: size.width * 0.90,
    );

    final overlay = Path()
      ..addRect(Offset.zero & size)
      ..addOval(oval)
      ..fillType = PathFillType.evenOdd;

    canvas.drawPath(
      overlay,
      Paint()..color = Colors.black.withValues(alpha: 0.38),
    );
    canvas.drawOval(
      oval,
      Paint()
        ..style = PaintingStyle.stroke
        ..strokeWidth = 3
        ..color = Colors.white.withValues(alpha: 0.9),
    );
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
