import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/app.dart';
import 'package:mobile/core/network/dio_provider.dart';
import 'package:mobile/core/theme/app_theme.dart';
import 'package:mobile/core/widgets/app_chrome.dart';
import 'package:mobile/core/widgets/siboru_mark.dart';
import 'package:mobile/features/onboarding/data/onboarding_storage.dart';

import '../../support/fake_backend.dart';

/// Never answers, so the app stays on its splash screen.
class PendingOnboardingStorage extends OnboardingStorage {
  @override
  Future<bool> isCompleted() => Completer<bool>().future;
}

Future<void> pumpApp(WidgetTester tester, OnboardingStorage storage) async {
  final container = ProviderContainer(
    overrides: [
      tokenStorageProvider.overrideWithValue(FakeTokenStorage()),
      onboardingStorageProvider.overrideWithValue(storage),
    ],
  );
  addTearDown(container.dispose);
  await tester.pumpWidget(
    UncontrolledProviderScope(container: container, child: const BookingApp()),
  );
  await tester.pump();
}

void main() {
  testWidgets('splash screen memakai nama dan logo Siboru', (tester) async {
    await pumpApp(tester, PendingOnboardingStorage());

    expect(find.text('Siboru'), findsOneWidget);
    expect(find.text('Sistem Peminjaman Ruangan'), findsOneWidget);
    expect(find.bySemanticsLabel('Logo Siboru'), findsOneWidget);
    expect(
      tester.widget<Scaffold>(find.byType(Scaffold)).backgroundColor,
      AppColors.primary,
    );
    // Centred like the native launch screen, so the logo does not jump.
    expect(
      tester.getCenter(find.byType(SiboruMark)),
      tester.getCenter(find.byType(Scaffold)),
    );
  });

  testWidgets('halaman login memakai logo Siboru', (tester) async {
    await pumpApp(tester, FakeOnboardingStorage(completed: true));
    await tester.pumpAndSettle();

    expect(find.byType(AppLogoMark), findsOneWidget);
    expect(find.bySemanticsLabel('Logo Siboru'), findsOneWidget);
    expect(find.text('Smart Room Booking'), findsNothing);
  });

  testWidgets('judul aplikasi adalah Siboru', (tester) async {
    await pumpApp(tester, FakeOnboardingStorage(completed: true));

    expect(
      tester.widget<MaterialApp>(find.byType(MaterialApp)).title,
      'Siboru',
    );
  });
}
