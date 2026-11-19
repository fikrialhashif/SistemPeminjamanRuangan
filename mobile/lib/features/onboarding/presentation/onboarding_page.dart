import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/widgets/app_chrome.dart';
import 'onboarding_controller.dart';

class OnboardingPage extends ConsumerStatefulWidget {
  const OnboardingPage({super.key});

  @override
  ConsumerState<OnboardingPage> createState() => _OnboardingPageState();
}

class _OnboardingPageState extends ConsumerState<OnboardingPage> {
  static const _steps = [
    _OnboardingStep(
      eyebrow: 'RESERVE & ASSET',
      title: 'Pesan ruangan dan aset dengan mudah',
      description: 'Temukan ketersediaan ruangan, ajukan booking, dan kelola peminjaman aset dalam satu aplikasi.',
      icon: Icons.meeting_room_rounded,
    ),
    _OnboardingStep(
      eyebrow: 'CHECK-IN AMAN',
      title: 'Verifikasi kehadiran di lokasi',
      description: 'Konfirmasi check-in dengan QR, lokasi, dan verifikasi wajah sesuai kebijakan keamanan kampus.',
      icon: Icons.qr_code_scanner_rounded,
    ),
    _OnboardingStep(
      eyebrow: 'KELOLA AKTIVITAS',
      title: 'Pantau booking dan notifikasi',
      description: 'Lihat jadwal, status persetujuan, pengingat, dan penalti tanpa perlu kembali ke meja admin.',
      icon: Icons.event_available_rounded,
    ),
  ];

  final _pageController = PageController();
  int _index = 0;
  bool _finishing = false;

  @override
  void dispose() {
    _pageController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final isLast = _index == _steps.length - 1;
    return Scaffold(
      body: SafeArea(
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 12, 12, 0),
              child: Row(
                children: [
                  const AppLogoMark(size: 40),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'RESERVE & ASSET',
                          style: Theme.of(context).textTheme.labelSmall,
                        ),
                        const SizedBox(height: 2),
                        Text(
                          'Smart Room Booking',
                          style: Theme.of(context).textTheme.titleMedium,
                        ),
                      ],
                    ),
                  ),
                  if (!isLast)
                    TextButton(
                      onPressed: _finishing ? null : _finish,
                      child: const Text('Lewati'),
                    )
                  else
                    const SizedBox(width: 64),
                ],
              ),
            ),
            Expanded(
              child: PageView.builder(
                controller: _pageController,
                itemCount: _steps.length,
                onPageChanged: (index) => setState(() => _index = index),
                itemBuilder: (context, index) =>
                    _OnboardingStepView(step: _steps[index]),
              ),
            ),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 24),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  for (var index = 0; index < _steps.length; index++)
                    AnimatedContainer(
                      duration: const Duration(milliseconds: 220),
                      margin: const EdgeInsets.symmetric(horizontal: 4),
                      width: index == _index ? 26 : 8,
                      height: 8,
                      decoration: BoxDecoration(
                        color: index == _index
                            ? AppColors.primary
                            : AppColors.border,
                        borderRadius: BorderRadius.circular(10),
                      ),
                    ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(24, 20, 24, 20),
              child: SizedBox(
                width: double.infinity,
                child: FilledButton(
                  onPressed: _finishing ? null : _next,
                  child: _finishing
                      ? const SizedBox.square(
                          dimension: 20,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.white,
                          ),
                        )
                      : Text(isLast ? 'Mulai sekarang' : 'Lanjut'),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _next() {
    if (_index == _steps.length - 1) {
      _finish();
      return;
    }
    _pageController.nextPage(
      duration: const Duration(milliseconds: 280),
      curve: Curves.easeOutCubic,
    );
  }

  Future<void> _finish() async {
    if (_finishing) return;
    setState(() => _finishing = true);
    try {
      await ref.read(onboardingControllerProvider.notifier).complete();
    } catch (_) {
      if (!mounted) return;
      setState(() => _finishing = false);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Gagal menyimpan status onboarding. Coba lagi.'),
          backgroundColor: AppColors.danger,
        ),
      );
    }
  }
}

class _OnboardingStepView extends StatelessWidget {
  const _OnboardingStepView({required this.step});

  final _OnboardingStep step;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(24, 12, 24, 8),
      child: Column(
        children: [
          Expanded(
            child: Center(
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 340),
                child: AspectRatio(
                  aspectRatio: 1.15,
                  child: _StepArtwork(icon: step.icon),
                ),
              ),
            ),
          ),
          const SizedBox(height: 24),
          Text(
            step.eyebrow,
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.labelSmall
                ?.copyWith(color: AppColors.primary, letterSpacing: 1.2),
          ),
          const SizedBox(height: 8),
          Text(
            step.title,
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.headlineSmall,
          ),
          const SizedBox(height: 10),
          Text(
            step.description,
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.bodyMedium
                ?.copyWith(color: AppColors.muted),
          ),
        ],
      ),
    );
  }
}

class _StepArtwork extends StatelessWidget {
  const _StepArtwork({required this.icon});

  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(32),
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [AppColors.primaryDark, AppColors.primary],
        ),
        boxShadow: [
          BoxShadow(
            color: AppColors.primary.withValues(alpha: 0.22),
            blurRadius: 24,
            offset: const Offset(0, 12),
          ),
        ],
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(32),
        child: Stack(
          children: [
            Positioned(
              top: -36,
              right: -28,
              child: _Bubble(size: 150, opacity: 0.09),
            ),
            Positioned(
              bottom: -52,
              left: -36,
              child: _Bubble(size: 180, opacity: 0.07),
            ),
            Positioned(
              top: 26,
              right: 28,
              child: Container(
                width: 18,
                height: 18,
                decoration: BoxDecoration(
                  color: AppColors.accent,
                  shape: BoxShape.circle,
                  border: Border.all(color: AppColors.mint, width: 2),
                ),
              ),
            ),
            Center(
              child: Container(
                width: 112,
                height: 112,
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.14),
                  borderRadius: BorderRadius.circular(28),
                  border: Border.all(
                    color: Colors.white.withValues(alpha: 0.2),
                  ),
                ),
                child: Icon(icon, size: 56, color: Colors.white),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Bubble extends StatelessWidget {
  const _Bubble({required this.size, required this.opacity});

  final double size;
  final double opacity;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: opacity),
        shape: BoxShape.circle,
      ),
    );
  }
}

class _OnboardingStep {
  const _OnboardingStep({
    required this.eyebrow,
    required this.title,
    required this.description,
    required this.icon,
  });

  final String eyebrow;
  final String title;
  final String description;
  final IconData icon;
}
