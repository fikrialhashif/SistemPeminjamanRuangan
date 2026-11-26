import 'dart:async';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/app.dart';
import 'package:mobile/core/network/dio_provider.dart';
import 'package:mobile/features/auth/presentation/auth_controller.dart';
import 'package:mobile/features/auth/presentation/login_page.dart';
import 'package:mobile/features/bookings/presentation/bundled_booking_page.dart';
import 'package:mobile/features/onboarding/data/onboarding_storage.dart';
import 'package:mobile/features/resources/presentation/resources_page.dart';

import '../../support/fake_backend.dart';

Future<ProviderContainer> startApp(
  WidgetTester tester, {
  required FakeTokenStorage storage,
  required FakeBackend backend,
}) async {
  final container = ProviderContainer(
    overrides: [
      tokenStorageProvider.overrideWithValue(storage),
      onboardingStorageProvider.overrideWithValue(
        FakeOnboardingStorage(completed: true),
      ),
    ],
  );
  addTearDown(container.dispose);
  container.read(dioProvider).httpClientAdapter = backend;
  await tester.pumpWidget(
    UncontrolledProviderScope(container: container, child: const BookingApp()),
  );
  await tester.pumpAndSettle();
  return container;
}

class OfflineBackend extends FakeBackend {
  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async => throw DioException(
    requestOptions: options,
    type: DioExceptionType.unknown,
  );
}

void main() {
  testWidgets('token tersimpan langsung membuka katalog', (tester) async {
    final container = await startApp(
      tester,
      storage: FakeTokenStorage()..token = 'masih-berlaku',
      backend: FakeBackend(),
    );

    expect(find.byType(ResourcesPage), findsOneWidget);
    expect(
      container.read(authControllerProvider).value?['user']['name'],
      'Budi',
    );
  });

  testWidgets('tanpa token menampilkan login tanpa memanggil server', (
    tester,
  ) async {
    final backend = FakeBackend();

    await startApp(tester, storage: FakeTokenStorage(), backend: backend);

    expect(find.byType(LoginPage), findsOneWidget);
    expect(backend.requests, isEmpty);
  });

  testWidgets('token yang ditolak server dihapus dan kembali ke login', (
    tester,
  ) async {
    final storage = FakeTokenStorage()..token = 'kedaluwarsa';

    await startApp(
      tester,
      storage: storage,
      backend: FakeBackend()..unauthorizedOnMe = true,
    );

    expect(find.byType(LoginPage), findsOneWidget);
    expect(storage.token, isNull);
  });

  testWidgets('gangguan jaringan menampilkan login dan menyimpan token', (
    tester,
  ) async {
    final storage = FakeTokenStorage()..token = 'masih-berlaku';

    await startApp(tester, storage: storage, backend: OfflineBackend());

    expect(find.byType(LoginPage), findsOneWidget);
    expect(storage.token, 'masih-berlaku');
  });

  testWidgets('sesi yang berakhir menghapus data pengguna', (tester) async {
    final backend = FakeBackend();
    final container = await startApp(
      tester,
      storage: FakeTokenStorage()..token = 'masih-berlaku',
      backend: backend,
    );

    backend.unauthorizedOnMe = true;
    // Awaiting directly would block: the widget test clock only advances on pump.
    Object? failure;
    unawaited(
      container
          .read(dioProvider)
          .get<Object?>('/me')
          .then<void>((_) {}, onError: (Object error) => failure = error),
    );
    await tester.pumpAndSettle();

    expect(failure, isA<DioException>());

    expect(find.byType(LoginPage), findsOneWidget);
    expect(container.read(authControllerProvider).value, isNull);
  });

  testWidgets('rute bundled membuka halaman booking bundled', (tester) async {
    await startApp(tester, storage: FakeTokenStorage(), backend: FakeBackend());
    final router = buildAppRouter();
    final container = ProviderContainer(
      overrides: [tokenStorageProvider.overrideWithValue(FakeTokenStorage())],
    );
    addTearDown(container.dispose);

    await tester.pumpWidget(
      UncontrolledProviderScope(
        container: container,
        child: MaterialApp.router(routerConfig: router),
      ),
    );
    router.go('/bookings/bundled');
    await tester.pump();
    await tester.pump(const Duration(seconds: 1));

    expect(find.byType(BundledBookingPage), findsOneWidget);
  });
}
