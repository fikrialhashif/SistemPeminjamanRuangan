import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:mobile/core/network/dio_provider.dart';
import 'package:mobile/core/permissions/app_permissions.dart';
import 'package:mobile/features/check_in/data/check_in_repository.dart';
import 'package:mobile/features/check_in/data/location_service.dart';
import 'package:mobile/features/check_in/presentation/check_in_page.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import '../../support/fake_backend.dart';

/// Hands out a new reading on every call, so a test can tell them apart.
class FakeLocationService extends LocationService {
  int readings = 0;

  @override
  Future<PermissionState> permissionState() async => PermissionState.granted;

  @override
  Future<LocationEvidence> currentEvidence() async {
    readings += 1;
    return LocationEvidence(
      latitude: 0.5 + readings / 1000,
      longitude: 101.4,
      accuracy: 8,
      timestamp: DateTime.utc(2026, 11, 26, 2, readings),
      isMock: false,
    );
  }
}

class LocationBackend extends FakeBackend {
  LocationBackend({
    this.inside = true,
    this.failCheck = false,
    this.resourceType,
  });

  bool inside;
  bool failCheck;
  String? resourceType;

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    final path = options.uri.path;
    if (path.endsWith('/location-check')) {
      requests.add(options);
      if (failCheck) return _json({'message': 'Server sibuk.'}, 500);
      return _json({
        'valid': inside,
        'rejection_reasons': inside
            ? <String>[]
            : ['gps: Distance 250m exceeds geofence radius of 100m'],
        'distance_meters': inside ? 20 : 250,
        'radius_meters': 100,
      }, 200);
    }
    if (resourceType != null && path.endsWith('/bookings/9')) {
      return _json({
        'id': 9,
        'status': 'approved',
        'resource': {'name': 'Contoh', 'type': resourceType},
      }, 200);
    }
    // No face step, so the flow can reach the confirmation in a widget test.
    if (path.endsWith('/face/status')) {
      return _json({'enabled': false, 'enrolled': false}, 200);
    }
    return super.fetch(options, requestStream, cancelFuture);
  }

  ResponseBody _json(Object body, int status) => ResponseBody.fromString(
    jsonEncode(body),
    status,
    headers: {
      Headers.contentTypeHeader: [Headers.jsonContentType],
    },
  );
}

Future<void> openCheckIn(
  WidgetTester tester,
  LocationBackend backend,
  FakeLocationService location,
) async {
  final container = ProviderContainer(
    overrides: [
      tokenStorageProvider.overrideWithValue(FakeTokenStorage()),
      locationServiceProvider.overrideWithValue(location),
    ],
  );
  addTearDown(container.dispose);
  container.read(dioProvider).httpClientAdapter = backend;
  final router = GoRouter(
    initialLocation: '/check-in',
    routes: [
      GoRoute(
        path: '/check-in',
        builder: (_, _) => const CheckInPage(bookingId: '9'),
      ),
      GoRoute(
        path: '/bookings/:id',
        builder: (_, _) => const Text('detail booking'),
      ),
    ],
  );
  await tester.pumpWidget(
    UncontrolledProviderScope(
      container: container,
      child: MaterialApp.router(routerConfig: router),
    ),
  );
  await tester.pumpAndSettle();
}

Future<void> tapButton(WidgetTester tester, String label) async {
  await tester.ensureVisible(find.text(label));
  await tester.tap(find.text(label));
  await tester.pumpAndSettle();
}

bool canContinue(WidgetTester tester) =>
    tester
        .widget<FilledButton>(find.widgetWithText(FilledButton, 'Lanjut'))
        .onPressed !=
    null;

void main() {
  testWidgets('di dalam area boleh lanjut ke langkah berikutnya', (
    tester,
  ) async {
    final backend = LocationBackend();
    await openCheckIn(tester, backend, FakeLocationService());

    await tapButton(tester, 'Ambil lokasi');

    expect(backend.lastJsonBody('/location-check')?['gps_latitude'], 0.501);
    expect(find.text('Lokasi Anda berada di area ruangan.'), findsOneWidget);
    expect(canContinue(tester), isTrue);
  });

  testWidgets('di luar radius tidak bisa lanjut dan jaraknya ditampilkan', (
    tester,
  ) async {
    await openCheckIn(
      tester,
      LocationBackend(inside: false),
      FakeLocationService(),
    );

    await tapButton(tester, 'Ambil lokasi');

    expect(
      find.textContaining('Anda berada di luar area yang diizinkan.'),
      findsOneWidget,
    );
    expect(
      find.textContaining('Jarak Anda 250 m, batas 100 m.'),
      findsOneWidget,
    );
    expect(canContinue(tester), isFalse);
  });

  testWidgets('memperbarui lokasi setelah masuk area membuka tombol lanjut', (
    tester,
  ) async {
    final backend = LocationBackend(inside: false);
    await openCheckIn(tester, backend, FakeLocationService());
    await tapButton(tester, 'Ambil lokasi');

    backend.inside = true;
    await tapButton(tester, 'Perbarui lokasi');

    expect(canContinue(tester), isTrue);
  });

  testWidgets('pemeriksaan yang gagal tidak membuka tombol lanjut', (
    tester,
  ) async {
    await openCheckIn(
      tester,
      LocationBackend(failCheck: true),
      FakeLocationService(),
    );

    await tapButton(tester, 'Ambil lokasi');

    expect(find.text('Server sibuk.'), findsOneWidget);
    expect(canContinue(tester), isFalse);
  });

  for (final entry in {'asset': 'QR aset', 'room': 'QR ruangan'}.entries) {
    testWidgets('booking ${entry.key} meminta ${entry.value}', (tester) async {
      await openCheckIn(
        tester,
        LocationBackend(resourceType: entry.key),
        FakeLocationService(),
      );
      await tapButton(tester, 'Ambil lokasi');

      await tapButton(tester, 'Lanjut');

      expect(find.text('Langkah 2 - ${entry.value}'), findsOneWidget);
    });
  }

  test('penolakan label aset menyebut aset, bukan ruangan', () {
    expect(
      checkInDecisionMessage({
        'result': 'rejected',
        'rejection_reasons': ['qr: Asset mismatch'],
      }),
      'QR ini bukan label aset yang Anda pinjam.',
    );
  });

  testWidgets('konfirmasi mengirim lokasi yang diambil ulang', (tester) async {
    final backend = LocationBackend();
    final location = FakeLocationService();
    await openCheckIn(tester, backend, location);
    await tapButton(tester, 'Ambil lokasi');
    await tapButton(tester, 'Lanjut');
    tester.widget<MobileScanner>(find.byType(MobileScanner)).onDetect!(
      const BarcodeCapture(barcodes: [Barcode(rawValue: 'qr-token')]),
    );
    await tester.pumpAndSettle();
    await tapButton(tester, 'Lanjut');
    await tapButton(tester, 'Lanjut');

    await tapButton(tester, 'Konfirmasi check-in');

    expect(location.readings, 2);
    final body = backend.lastJsonBody('/bookings/9/check-in');
    expect(body?['gps_latitude'], 0.502);
    expect(body?['gps_timestamp'], '2026-11-26T02:02:00.000Z');
  });
}
