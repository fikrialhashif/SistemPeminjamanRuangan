import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/core/network/dio_provider.dart';
import 'package:mobile/features/bookings/presentation/booking_form_page.dart';
import 'package:mobile/features/resources/presentation/resource_providers.dart';

import '../../support/fake_backend.dart';

/// The asset face setting is off and the user has not enrolled.
class FaceSettingOffBackend extends FakeBackend {
  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    if (options.uri.path.endsWith('/face/status')) {
      return ResponseBody.fromString(
        jsonEncode({'enabled': false, 'enrolled': false}),
        200,
        headers: {
          Headers.contentTypeHeader: [Headers.jsonContentType],
        },
      );
    }
    return super.fetch(options, requestStream, cancelFuture);
  }
}

const banner =
    'Wajah belum terdaftar. Daftarkan wajah sebelum booking ruangan.';

Future<void> openForm(WidgetTester tester, String type) async {
  final container = ProviderContainer(
    overrides: [
      tokenStorageProvider.overrideWithValue(FakeTokenStorage()),
      resourceDetailProvider(
        '1',
      ).overrideWith((ref) async => {'id': 1, 'name': 'Contoh', 'type': type}),
    ],
  );
  addTearDown(container.dispose);
  container.read(dioProvider).httpClientAdapter = FaceSettingOffBackend();
  await tester.pumpWidget(
    UncontrolledProviderScope(
      container: container,
      child: const MaterialApp(home: BookingFormPage(resourceId: '1')),
    ),
  );
  await tester.pumpAndSettle();
}

void main() {
  testWidgets('ruangan selalu meminta pendaftaran wajah', (tester) async {
    await openForm(tester, 'room');

    expect(find.text(banner), findsOneWidget);
  });

  testWidgets('aset mengikuti setting verifikasi wajah', (tester) async {
    await openForm(tester, 'asset');

    expect(find.text(banner), findsNothing);
  });
}
