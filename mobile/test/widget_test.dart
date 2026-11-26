import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/app.dart';
import 'package:mobile/core/network/dio_provider.dart';
import 'package:mobile/features/onboarding/data/onboarding_storage.dart';

import 'support/fake_backend.dart';

void main() {
  testWidgets('shows login shell for returning users', (tester) async {
    final container = ProviderContainer(
      overrides: [
        // Startup reads the stored token; the platform store is unavailable here.
        tokenStorageProvider.overrideWithValue(FakeTokenStorage()),
        onboardingStorageProvider.overrideWithValue(
          FakeOnboardingStorage(completed: true),
        ),
      ],
    );
    addTearDown(container.dispose);

    await tester.pumpWidget(
      UncontrolledProviderScope(
        container: container,
        child: const BookingApp(),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Masuk'), findsWidgets);
  });
}
