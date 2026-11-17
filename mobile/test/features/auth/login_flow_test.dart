import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/app.dart';
import 'package:mobile/core/network/dio_provider.dart';

import '../../support/fake_backend.dart';

void main() {
  testWidgets('login membawa pengguna ke katalog resource', (tester) async {
    final backend = FakeBackend();
    final storage = FakeTokenStorage();
    final container = ProviderContainer(
      overrides: [tokenStorageProvider.overrideWithValue(storage)],
    );
    addTearDown(container.dispose);
    container.read(dioProvider).httpClientAdapter = backend;

    await tester.pumpWidget(
      UncontrolledProviderScope(
        container: container,
        child: const BookingApp(),
      ),
    );

    expect(find.text('Masuk'), findsWidgets);
    await tester.enterText(
      find.byType(TextFormField).at(0),
      'budi@example.com',
    );
    await tester.enterText(find.byType(TextFormField).at(1), 'secret');
    await tester.tap(find.widgetWithText(FilledButton, 'Masuk'));
    await tester.pumpAndSettle();

    expect(find.text('Resource'), findsOneWidget);
    expect(find.text('Ruang Rapat A'), findsOneWidget);
    expect(storage.token, 'test-token');
  });
}
