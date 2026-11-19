import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/core/network/dio_provider.dart';
import 'package:mobile/core/theme/app_theme.dart';
import 'package:mobile/features/auth/presentation/auth_controller.dart';
import 'package:mobile/features/profile/presentation/profile_page.dart';

import '../../support/fake_backend.dart';

void main() {
  Future<FakeBackend> pumpProfile(
    WidgetTester tester,
    ProviderContainer container,
  ) async {
    tester.view.physicalSize = const Size(800, 1600);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    final backend = FakeBackend();
    container.read(dioProvider).httpClientAdapter = backend;
    await tester.runAsync(
      () => container
          .read(authControllerProvider.notifier)
          .login('budi@example.com', 'secret'),
    );

    await tester.pumpWidget(
      UncontrolledProviderScope(
        container: container,
        child: MaterialApp(theme: AppTheme.light, home: const ProfilePage()),
      ),
    );
    await tester.pumpAndSettle();
    return backend;
  }

  ProviderContainer createContainer() {
    final container = ProviderContainer(
      overrides: [tokenStorageProvider.overrideWithValue(FakeTokenStorage())],
    );
    addTearDown(container.dispose);
    return container;
  }

  testWidgets('profil menampilkan nama pengguna dan menyimpan nama baru', (
    tester,
  ) async {
    final container = createContainer();
    final backend = await pumpProfile(tester, container);

    expect(find.text('Budi'), findsWidgets);

    await tester.enterText(find.byType(TextFormField).at(0), 'Budi Santoso');
    await tester.tap(find.widgetWithText(FilledButton, 'Simpan nama'));
    await tester.pumpAndSettle();

    expect(backend.lastJsonBody('/me/profile')?['name'], 'Budi Santoso');
    expect(find.text('Budi Santoso'), findsWidgets);
  });

  testWidgets('profil mengirim permintaan ubah password dengan benar', (
    tester,
  ) async {
    final container = createContainer();
    final backend = await pumpProfile(tester, container);

    await tester.enterText(find.byType(TextFormField).at(1), 'password');
    await tester.enterText(find.byType(TextFormField).at(2), 'new-password');
    await tester.enterText(find.byType(TextFormField).at(3), 'new-password');
    final submit = find.widgetWithText(FilledButton, 'Perbarui password');
    await tester.ensureVisible(submit);
    await tester.tap(submit);
    await tester.pumpAndSettle();

    final body = backend.lastJsonBody('/me/password');
    expect(body?['current_password'], 'password');
    expect(body?['password'], 'new-password');
    expect(body?['password_confirmation'], 'new-password');
  });
}
