import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/widgets/app_chrome.dart';
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

class _StartupSplash extends StatelessWidget {
  const _StartupSplash();

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const AppLogoMark(size: 72),
            const SizedBox(height: 18),
            Text(
              'RESERVE & ASSET',
              style: Theme.of(context).textTheme.labelSmall,
            ),
            const SizedBox(height: 6),
            Text(
              'Smart Room Booking',
              style: Theme.of(context).textTheme.titleMedium,
            ),
            const SizedBox(height: 26),
            const SizedBox.square(
              dimension: 24,
              child: CircularProgressIndicator(
                strokeWidth: 2.4,
                color: AppColors.primary,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
