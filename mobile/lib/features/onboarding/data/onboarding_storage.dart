import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

final onboardingStorageProvider = Provider<OnboardingStorage>(
  (ref) => const OnboardingStorage(),
);

class OnboardingStorage {
  const OnboardingStorage();

  static const _key = 'onboarding_completed_v1';
  static const _storage = FlutterSecureStorage();

  Future<bool> isCompleted() async => await _storage.read(key: _key) == 'true';

  Future<void> markCompleted() => _storage.write(key: _key, value: 'true');
}
