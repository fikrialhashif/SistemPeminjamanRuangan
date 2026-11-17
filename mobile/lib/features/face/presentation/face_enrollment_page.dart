import 'dart:typed_data';

import 'package:camera/camera.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_error.dart';

import '../data/face_repository.dart';

class FaceEnrollmentPage extends ConsumerStatefulWidget {
  const FaceEnrollmentPage({super.key});
  @override
  ConsumerState<FaceEnrollmentPage> createState() => _FaceEnrollmentPageState();
}

class _FaceEnrollmentPageState extends ConsumerState<FaceEnrollmentPage> {
  CameraController? camera;
  bool consent = false;
  bool loading = false;
  String? message;

  @override
  void initState() {
    super.initState();
    _init();
  }

  Future<void> _init() async {
    final cameras = await availableCameras();
    if (cameras.isNotEmpty && mounted) {
      final controller = CameraController(
        cameras.first,
        ResolutionPreset.medium,
        enableAudio: false,
      );
      await controller.initialize();
      if (mounted) setState(() => camera = controller);
    }
  }

  @override
  void dispose() {
    camera?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Daftar wajah')),
    body: ListView(
      padding: const EdgeInsets.all(16),
      children: [
        const Text(
          'Wajah dipakai untuk verifikasi check-in. Data diproses server dan dapat dihapus kapan saja.',
        ),
        CheckboxListTile(
          value: consent,
          onChanged: (value) => setState(() => consent = value ?? false),
          title: const Text('Saya menyetujui penggunaan data wajah'),
        ),
        if (camera?.value.isInitialized == true)
          AspectRatio(
            aspectRatio: camera!.value.aspectRatio,
            child: CameraPreview(camera!),
          ),
        FilledButton(
          onPressed: !consent || loading || camera == null ? null : _enroll,
          child: loading
              ? const CircularProgressIndicator()
              : const Text('Ambil sampel dan daftar'),
        ),
        OutlinedButton(
          onPressed: loading ? null : _delete,
          child: const Text('Hapus data wajah'),
        ),
        if (message != null) Text(message!),
      ],
    ),
  );

  Future<void> _enroll() async {
    setState(() {
      loading = true;
      message = null;
    });
    try {
      final frames = <Uint8List>[];
      for (var i = 0; i < 3; i++) {
        frames.add(await ref.read(faceRepositoryProvider).capture(camera!));
        await Future<void>.delayed(const Duration(milliseconds: 300));
      }
      await ref.read(faceRepositoryProvider).enroll(frames);
      setState(() => message = 'Enrollment berhasil.');
    } catch (error) {
      setState(() => message = apiErrorMessage(error));
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> _delete() async {
    try {
      await ref.read(faceRepositoryProvider).delete();
      if (mounted) setState(() => message = 'Data wajah dihapus.');
    } catch (error) {
      if (mounted) setState(() => message = apiErrorMessage(error));
    }
  }
}
