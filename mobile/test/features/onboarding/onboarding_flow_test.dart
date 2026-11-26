import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/app.dart';
import 'package:mobile/core/network/dio_provider.dart';
import 'package:mobile/features/onboarding/data/onboarding_storage.dart';

import '../../support/fake_backend.dart';

void main() {
  Future<ProviderContainer> pumpApp(
    WidgetTester tester,
    FakeOnboardingStorage storage,
  ) async {
    final container = ProviderContainer(
      overrides: [
        // Startup reads the stored token; the platform store is unavailable here.
        tokenStorageProvider.overrideWithValue(FakeTokenStorage()),
        onboardingStorageProvider.overrideWithValue(storage),
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
    return container;
  }

  testWidgets('onboarding tampil sebelum login dan tersimpan setelah selesai', (
    tester,
  ) async {
    final storage = FakeOnboardingStorage();
    await pumpApp(tester, storage);

    expect(find.text('Pesan ruangan dan aset dengan mudah'), findsOneWidget);
    expect(find.text('Masuk'), findsNothing);

    await tester.tap(find.text('Lanjut'));
    await tester.pumpAndSettle();
    expect(find.text('Verifikasi kehadiran di lokasi'), findsOneWidget);

    await tester.tap(find.text('Lanjut'));
    await tester.pumpAndSettle();
    expect(find.text('Pantau booking dan notifikasi'), findsOneWidget);

    await tester.tap(find.text('Mulai sekarang'));
    await tester.pumpAndSettle();

    expect(storage.completed, isTrue);
    expect(find.text('Masuk'), findsWidgets);
  });

  testWidgets('onboarding tidak muncul lagi setelah pernah diselesaikan', (
    tester,
  ) async {
    final storage = FakeOnboardingStorage(completed: true);
    await pumpApp(tester, storage);

    expect(find.text('Masuk'), findsWidgets);
    expect(find.text('Pesan ruangan dan aset dengan mudah'), findsNothing);
  });

  testWidgets('tombol lewati menyimpan status onboarding', (tester) async {
    final storage = FakeOnboardingStorage();
    await pumpApp(tester, storage);

    await tester.tap(find.text('Lewati'));
    await tester.pumpAndSettle();

    expect(storage.completed, isTrue);
    expect(find.text('Masuk'), findsWidgets);
  });
}
