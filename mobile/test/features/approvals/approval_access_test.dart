import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/app.dart';
import 'package:mobile/core/network/dio_provider.dart';
import 'package:mobile/core/widgets/app_chrome.dart';
import 'package:mobile/features/onboarding/data/onboarding_storage.dart';

import '../../support/fake_backend.dart';

void main() {
  Future<void> login(WidgetTester tester, List<String> roles) async {
    final backend = FakeBackend()..loginRoleNames = roles;
    final container = ProviderContainer(
      overrides: [
        tokenStorageProvider.overrideWithValue(FakeTokenStorage()),
        onboardingStorageProvider.overrideWithValue(
          FakeOnboardingStorage(completed: true),
        ),
      ],
    );
    addTearDown(container.dispose);
    container.read(dioProvider).httpClientAdapter = backend;

    await tester.pumpWidget(
      UncontrolledProviderScope(
        container: container,
        child: const BookingApp(),
      ),
    );
    await tester.pumpAndSettle();
    await tester.enterText(
      find.byType(TextFormField).at(0),
      'budi@example.com',
    );
    await tester.enterText(find.byType(TextFormField).at(1), 'secret');
    await tester.tap(find.widgetWithText(FilledButton, 'Masuk'));
    await tester.pumpAndSettle();
  }

  testWidgets('akun approver memiliki menu persetujuan di navigasi bawah', (
    tester,
  ) async {
    await login(tester, ['approver']);

    final menu = find.descendant(
      of: find.byType(AppBottomNav),
      matching: find.text('Persetujuan'),
    );
    expect(menu, findsOneWidget);

    await tester.tap(menu);
    await tester.pumpAndSettle();

    expect(find.text('Pengajuan menunggu'), findsOneWidget);
    expect(find.text('Ruang Rapat A'), findsOneWidget);

    await tester.tap(find.text('Ruang Rapat A'));
    await tester.pumpAndSettle();
    expect(find.text('KEPUTUSAN PERSETUJUAN'), findsOneWidget);

    await tester.binding.handlePopRoute();
    await tester.pumpAndSettle();
    expect(find.text('KEPUTUSAN PERSETUJUAN'), findsNothing);
    expect(find.text('Pengajuan menunggu'), findsOneWidget);

    await tester.tap(find.text('Ruang Rapat A'));
    await tester.pumpAndSettle();
    expect(find.text('KEPUTUSAN PERSETUJUAN'), findsOneWidget);

    await tester.tap(find.text('Setujui'));
    await tester.pumpAndSettle();
    expect(find.text('KEPUTUSAN PERSETUJUAN'), findsNothing);
  });

  testWidgets('akun anggota tidak melihat menu persetujuan', (tester) async {
    await login(tester, ['member']);

    final menu = find.descendant(
      of: find.byType(AppBottomNav),
      matching: find.text('Persetujuan'),
    );
    expect(menu, findsNothing);
  });
}
