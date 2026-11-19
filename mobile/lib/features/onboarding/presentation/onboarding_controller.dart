import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../data/onboarding_storage.dart';

final onboardingControllerProvider =
    AsyncNotifierProvider<OnboardingController, bool>(OnboardingController.new);

class OnboardingController extends AsyncNotifier<bool> {
  @override
  Future<bool> build() => ref.read(onboardingStorageProvider).isCompleted();

  Future<void> complete() async {
    await ref.read(onboardingStorageProvider).markCompleted();
    state = const AsyncData(true);
  }
}
