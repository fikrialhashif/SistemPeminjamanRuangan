import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/widgets/siboru_mark.dart';
import '../../auth/presentation/login_page.dart';
import 'onboarding_controller.dart';
import 'onboarding_page.dart';

class StartupPage extends ConsumerWidget {
  const StartupPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final completed = ref.watch(onboardingControllerProvider);
    return completed.when(
      loading: () => const _StartupSplash(),
      error: (_, _) => const OnboardingPage(),
      data: (done) => done ? const LoginPage() : const OnboardingPage(),
    );
  }
}

/// Continues the Android launch screen: same colour, with the mark in the same
/// place and at the same size, so only the name and spinner appear.
class _StartupSplash extends StatelessWidget {
  const _StartupSplash();

  static const _markSize = 160.0;

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    return Scaffold(
      backgroundColor: AppColors.primary,
      body: Stack(
        children: [
          const Center(child: SiboruMark(size: _markSize)),
          Align(
            alignment: Alignment.bottomCenter,
            child: SafeArea(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(24, 0, 24, 48),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      'Siboru',
                      style: textTheme.headlineSmall?.copyWith(
                        color: Colors.white,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      'Sistem Peminjaman Ruangan',
                      textAlign: TextAlign.center,
                      style: textTheme.bodyMedium?.copyWith(
                        color: Colors.white70,
                      ),
                    ),
                    const SizedBox(height: 24),
                    const SizedBox.square(
                      dimension: 24,
                      child: CircularProgressIndicator(
                        strokeWidth: 2.4,
                        color: AppColors.accent,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
