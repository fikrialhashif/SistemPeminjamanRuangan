import 'dart:convert';
import 'dart:typed_data';

import 'package:camera/camera.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import 'package:permission_handler/permission_handler.dart';

import '../../../core/network/api_error.dart';
import '../../../core/permissions/app_permissions.dart';
import '../../../core/widgets/camera_preview_box.dart';
import '../../bookings/presentation/booking_providers.dart';
import '../../face/data/face_repository.dart';
import '../data/check_in_repository.dart';
import '../data/location_service.dart';

class CheckInPage extends ConsumerStatefulWidget {
  const CheckInPage({required this.bookingId, super.key});
  final String bookingId;

  @override
  ConsumerState<CheckInPage> createState() => _CheckInPageState();
}

class _CheckInPageState extends ConsumerState<CheckInPage> {
  static const _steps = ['Lokasi', 'QR', 'Wajah'];

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
  Uint8List? faceFrame;

  bool get faceRequired => faceEnabled;

  @override
  void dispose() {
    faceFrame = null;
    camera?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final colorScheme = Theme.of(context).colorScheme;
    return Scaffold(
      appBar: AppBar(title: const Text('Konfirmasi booking')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          _StepHeader(steps: _steps, current: step),
          const SizedBox(height: 20),
          AnimatedSwitcher(
            duration: const Duration(milliseconds: 200),
            child: switch (step) {
              0 => _locationStep(),
              1 => _qrStep(),
              _ => _faceStep(),
            },
          ),
          const SizedBox(height: 24),
          if (message != null) _resultCard(colorScheme),
          const SizedBox(height: 8),
          _navigation(colorScheme),
        ],
      ),
    );
  }

  Widget _locationStep() {
    final ready = location != null;
    return _StepCard(
      key: const ValueKey('location-step'),
      icon: Icons.my_location,
      title: 'Langkah 1 - Lokasi',
      description: 'Aktifkan GPS dan izinkan akses lokasi. Server akan memeriksa apakah Anda berada di dalam area ruangan.',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _StatusLine(
            ready: ready,
            readyText: 'Lokasi berhasil diambil.',
            pendingText: 'Lokasi belum diambil.',
          ),
          if (location != null)
            Padding(
              padding: const EdgeInsets.only(top: 8),
              child: Text(
                'Akurasi perkiraan: ${location!.accuracy.toStringAsFixed(0)} m',
              ),
            ),
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
      description: 'Server akan meminta Anda menoleh kiri atau kanan. Lakukan gerakan tersebut, lalu ambil foto wajah Anda. Foto yang tidak sesuai data wajah akan ditolak.',
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
          if (faceEnabled && !faceEnrolled && !faceStatusLoading)
            _EnrollmentPrompt(onEnroll: _openEnrollment),
          if (faceEnabled && faceEnrolled) ...[
            if (challenge != null)
              _InfoBanner(
                icon: Icons.remove_red_eye_outlined,
                message:
                    'Gerakan diminta: ${faceChallengeInstruction(challenge!['challenge_type']?.toString())}.',
              ),
            const SizedBox(height: 12),
            if (camera?.value.isInitialized == true)
              SizedBox(
                height: 300,
                width: double.infinity,
                child: CameraPreviewBox(controller: camera!, borderRadius: 12),
              ),
            const SizedBox(height: 12),
            if (faceFrame == null)
              FilledButton.icon(
                onPressed: camera == null ? null : _captureFace,
                icon: const Icon(Icons.camera_alt),
                label: const Text('Ambil foto wajah'),
              )
            else
              Row(
                children: [
                  const Icon(Icons.check_circle, color: Colors.green),
                  const SizedBox(width: 8),
                  const Expanded(child: Text('Foto wajah siap dikirim.')),
                  TextButton(
                    onPressed: () => setState(() => faceFrame = null),
                    child: const Text('Ulangi'),
                  ),
                ],
              ),
          ],
        ],
      ),
    );
  }

  Widget _navigation(ColorScheme colorScheme) {
    final canContinue = switch (step) {
      0 => location != null,
      1 => qrToken != null,
      _ =>
        location != null &&
            qrToken != null &&
            faceStatusReady &&
            (!faceRequired || (faceEnrolled && faceFrame != null)),
    };

    return Row(
      children: [
        if (step > 0)
          OutlinedButton(
            onPressed: submitting ? null : _goBack,
            child: const Text('Kembali'),
          ),
        const Spacer(),
        if (step < 2)
          FilledButton(
            onPressed: canContinue ? _nextStep : null,
            child: const Text('Lanjut'),
          )
        else
          FilledButton.icon(
            onPressed: canContinue && !submitting ? _submit : null,
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
    if (step == 2) _prepareFaceStep();
  }

  void _goBack() {
    if (step == 2) {
      final controller = camera;
      setState(() {
        camera = null;
        faceFrame = null;
        challenge = null;
        faceStatusReady = false;
        step = 1;
      });
      controller?.dispose();
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
    setState(() {
      faceStatusLoading = true;
      faceStatusReady = false;
      faceError = null;
      faceFrame = null;
    });
    try {
      final faceRepository = ref.read(faceRepositoryProvider);
      final status = await faceRepository.status();
      if (!mounted || step != 2) return;
      final enabled = status['enabled'] == true;
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
        imageFormatGroup: ImageFormatGroup.jpeg,
      );
      await controller.initialize();
      if (!mounted || step != 2) {
        await controller.dispose();
        return;
      }
      setState(() => camera = controller);
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

  Future<void> _captureFace() async {
    final controller = camera;
    if (controller == null) return;
    try {
      final frame = await ref.read(faceRepositoryProvider).capture(controller);
      if (!mounted) return;
      setState(() {
        faceFrame = frame;
        faceError = null;
        message = null;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() => faceError = apiErrorMessage(error));
    }
  }

  Future<void> _openEnrollment() async {
    await context.push('/face/enrollment');
    if (mounted) await _prepareFaceStep();
  }

  Future<void> _submit() async {
    if (location == null || qrToken == null || !faceStatusReady) return;
    if (faceRequired && !faceEnrolled) {
      setState(() {
        accepted = false;
        message = 'Wajah belum terdaftar. Daftarkan wajah terlebih dahulu.';
      });
      return;
    }
    if (faceRequired && faceFrame == null) {
      setState(() {
        accepted = false;
        message = 'Ambil foto wajah terlebih dahulu.';
      });
      return;
    }

    setState(() {
      submitting = true;
      message = null;
    });

    final challengeType = challenge?['challenge_type']?.toString();
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
            faceChallenge: challengeType,
            faceChallengeResponse: challengeType == null
                ? null
                : {'action': challengeType},
          );
      final state =
          result['result']?.toString() ?? result['status']?.toString();
      final isAccepted = state == 'accepted';
      if (!mounted) return;
      setState(() {
        accepted = isAccepted;
        message = checkInDecisionMessage(result);
        if (!isAccepted) faceFrame = null;
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

      if (faceEnabled) await _renewChallenge();
    } catch (error) {
      if (!mounted) return;
      setState(() {
        accepted = false;
        message = apiErrorMessage(error);
      });
    } finally {
      if (mounted) setState(() => submitting = false);
    }
  }
}

class _StepHeader extends StatelessWidget {
  const _StepHeader({required this.steps, required this.current});

  final List<String> steps;
  final int current;

  @override
  Widget build(BuildContext context) {
    final colorScheme = Theme.of(context).colorScheme;
    return Row(
      children: [
        for (var index = 0; index < steps.length; index++) ...[
          Expanded(
            child: Column(
              children: [
                CircleAvatar(
                  radius: 16,
                  backgroundColor: index <= current
                      ? colorScheme.primary
                      : colorScheme.surfaceContainerHighest,
                  child: index < current
                      ? Icon(
                          Icons.check,
                          size: 18,
                          color: colorScheme.onPrimary,
                        )
                      : Text(
                          '${index + 1}',
                          style: TextStyle(
                            color: index <= current
                                ? colorScheme.onPrimary
                                : colorScheme.onSurfaceVariant,
                          ),
                        ),
                ),
                const SizedBox(height: 6),
                Text(
                  steps[index],
                  style: Theme.of(context).textTheme.labelMedium,
                ),
              ],
            ),
          ),
          if (index < steps.length - 1)
            Container(
              height: 2,
              width: 24,
              color: index < current
                  ? colorScheme.primary
                  : colorScheme.surfaceContainerHighest,
            ),
        ],
      ],
    );
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
