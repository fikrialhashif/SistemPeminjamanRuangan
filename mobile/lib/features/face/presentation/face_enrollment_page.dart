import 'dart:typed_data';

import 'package:camera/camera.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:permission_handler/permission_handler.dart';

import '../../../core/network/api_error.dart';
import '../../../core/permissions/app_permissions.dart';
import '../../../core/widgets/camera_preview_box.dart';
import '../data/face_repository.dart';

class FaceEnrollmentPage extends ConsumerStatefulWidget {
  const FaceEnrollmentPage({super.key});

  @override
  ConsumerState<FaceEnrollmentPage> createState() => _FaceEnrollmentPageState();
}

class _FaceEnrollmentPageState extends ConsumerState<FaceEnrollmentPage> {
  static const _steps = ['turn_left', 'turn_right', 'front'];

  CameraController? camera;
  bool consent = false;
  bool initializing = true;
  bool capturing = false;
  bool enrolling = false;
  bool alreadyEnrolled = false;
  bool canOpenSettings = false;
  int stepIndex = 0;
  final List<Uint8List> frames = [];
  final List<String> poses = [];
  String? status;
  String? error;

  @override
  void initState() {
    super.initState();
    _initCamera();
  }

  @override
  void dispose() {
    camera?.dispose();
    super.dispose();
  }

  Future<void> _initCamera() async {
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
        imageFormatGroup: ImageFormatGroup.jpeg,
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

  String _instruction(String pose) => switch (pose) {
    'turn_left' => 'Tolehkan kepala ke kiri, lalu tahan sampai foto diambil.',
    'turn_right' => 'Tolehkan kepala ke kanan, lalu tahan sampai foto diambil.',
    _ => 'Hadap lurus ke kamera, lalu tahan sampai foto diambil.',
  };

  String _stepLabel(String pose) => switch (pose) {
    'turn_left' => 'Toleh kiri',
    'turn_right' => 'Toleh kanan',
    _ => 'Hadap lurus',
  };

  Future<void> _captureStep() async {
    final controller = camera;
    if (controller == null || capturing || enrolling) return;

    setState(() {
      capturing = true;
      error = null;
      status = 'Mengambil sampel $_currentStepLabel...';
    });

    try {
      final frame = await ref.read(faceRepositoryProvider).capture(controller);
      if (!mounted) return;

      final pose = _steps[stepIndex];
      setState(() {
        frames.add(frame);
        poses.add(pose);
        stepIndex++;
        capturing = false;
        status = stepIndex < _steps.length
            ? 'Sampel tersimpan. ${_instruction(_steps[stepIndex])}'
            : 'Semua sampel terkumpul. Menyimpan data wajah...';
      });

      if (stepIndex == _steps.length) await _enroll();
    } catch (captureError) {
      if (!mounted) return;
      setState(() {
        capturing = false;
        status = null;
        error = apiErrorMessage(captureError);
      });
    }
  }

  String get _currentStepLabel => _stepLabelOf(_steps[stepIndex]);

  String _stepLabelOf(String pose) => switch (pose) {
    'turn_left' => 'toleh kiri',
    'turn_right' => 'toleh kanan',
    _ => 'hadap lurus',
  };

  Future<void> _enroll() async {
    if (enrolling || frames.isEmpty) return;

    setState(() {
      enrolling = true;
      error = null;
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
      });
    }
  }

  void _reset() {
    setState(() {
      frames.clear();
      poses.clear();
      stepIndex = 0;
      status = null;
      error = null;
    });
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

    try {
      await ref.read(faceRepositoryProvider).delete();
      if (!mounted) return;
      setState(() {
        alreadyEnrolled = false;
        _reset();
        status = 'Data wajah dihapus.';
      });
    } catch (deleteError) {
      if (!mounted) return;
      setState(() => error = apiErrorMessage(deleteError));
    }
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
    final pose = stepIndex < _steps.length ? _steps[stepIndex] : 'front';
    final instruction = stepIndex < _steps.length
        ? _instruction(pose)
        : 'Menyimpan data wajah...';

    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16),
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      decoration: BoxDecoration(
        color: Colors.black.withValues(alpha: 0.55),
        borderRadius: BorderRadius.circular(16),
      ),
      child: Row(
        children: [
          Icon(
            pose == 'front'
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
                  stepIndex < _steps.length
                      ? 'Langkah ${stepIndex + 1} dari ${_steps.length}: ${_stepLabel(pose)}'
                      : 'Menyimpan data wajah...',
                  style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.bold,
                    height: 1.35,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  instruction,
                  style: const TextStyle(color: Colors.white, height: 1.35),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _controls() {
    final scheme = Theme.of(context).colorScheme;
    final allCaptured = stepIndex == _steps.length;

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
            onChanged: enrolling || capturing
                ? null
                : (value) => setState(() => consent = value ?? false),
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
          if (stepIndex > 0 || enrolling) ...[
            const SizedBox(height: 12),
            LinearProgressIndicator(value: stepIndex / _steps.length),
            const SizedBox(height: 6),
            Text(
              'Sampel $stepIndex dari ${_steps.length}',
              style: const TextStyle(color: Colors.white70, fontSize: 12),
            ),
          ],
          const SizedBox(height: 12),
          FilledButton.icon(
            onPressed: !consent || capturing || enrolling || allCaptured
                ? null
                : _captureStep,
            icon: capturing
                ? const SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(Icons.camera_front),
            label: Text(
              capturing
                  ? 'Mengambil...'
                  : enrolling || allCaptured
                  ? 'Menyimpan...'
                  : 'Ambil sampel ${stepIndex + 1}/${_steps.length}',
            ),
          ),
          if (stepIndex > 0 && !enrolling && !capturing)
            TextButton(
              onPressed: _reset,
              child: const Text(
                'Ulangi dari awal',
                style: TextStyle(color: Colors.white70),
              ),
            ),
          if (alreadyEnrolled && !enrolling && stepIndex == 0)
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
