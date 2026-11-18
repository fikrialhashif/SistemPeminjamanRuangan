import 'dart:convert';
import 'dart:typed_data';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/core/network/api_error.dart';
import 'package:mobile/core/network/dio_provider.dart';
import 'package:mobile/core/session/session_expiry.dart';
import 'package:mobile/features/auth/data/auth_repository.dart';
import 'package:mobile/features/bookings/data/booking_repository.dart';
import 'package:mobile/features/check_in/data/check_in_repository.dart';
import 'package:mobile/features/check_in/data/location_service.dart';
import 'package:mobile/features/face/data/face_repository.dart';
import 'package:mobile/features/resources/data/resource_repository.dart';

import '../support/fake_backend.dart';

void main() {
  late FakeBackend backend;
  late FakeTokenStorage storage;
  late ProviderContainer container;

  setUp(() {
    backend = FakeBackend();
    storage = FakeTokenStorage();
    container = ProviderContainer(
      overrides: [tokenStorageProvider.overrideWithValue(storage)],
    );
    container.read(dioProvider).httpClientAdapter = backend;
  });

  tearDown(() => container.dispose());

  test('login -> katalog -> booking -> check-in tiga faktor', () async {
    final login = await container
        .read(authRepositoryProvider)
        .login('budi@example.com', 'secret');
    expect(login['token'], 'test-token');
    expect(storage.token, 'test-token');

    final resources = await container
        .read(resourceRepositoryProvider)
        .list(search: 'Rapat');
    expect(resources.single['name'], 'Ruang Rapat A');

    final booking = await container
        .read(bookingRepositoryProvider)
        .create(
          resourceId: '1',
          startsAt: DateTime.utc(2026, 11, 17, 2),
          endsAt: DateTime.utc(2026, 11, 17, 3),
          title: 'Rapat tim',
        );
    expect(booking['id'], 9);

    final location = LocationEvidence(
      latitude: 0.5071,
      longitude: 101.4478,
      accuracy: 8,
      timestamp: DateTime.utc(2026, 11, 17, 2),
      isMock: false,
    );

    final attempt = await container
        .read(checkInRepositoryProvider)
        .checkIn(
          bookingId: '9',
          qrToken: 'signed-qr-token',
          location: location,
        );
    expect(attempt['result'], 'accepted');

    final checkInBody = backend.lastJsonBody('/bookings/9/check-in')!;
    expect(checkInBody['qr_token'], 'signed-qr-token');
    expect(checkInBody['gps_latitude'], 0.5071);
    expect(checkInBody['gps_longitude'], 101.4478);
    expect(checkInBody['gps_accuracy'], 8);
    expect(checkInBody['gps_is_mock'], false);
    expect(checkInBody.containsKey('gps_timestamp'), isTrue);

    final faceStatus = await container.read(faceRepositoryProvider).status();
    expect(faceStatus['enrolled'], true);
    expect(faceStatus['enabled'], true);

    final challenge = await container.read(faceRepositoryProvider).challenge();
    expect(challenge['challenge_type'], 'turn');

    final faceAttempt = await container
        .read(checkInRepositoryProvider)
        .checkIn(
          bookingId: '9',
          qrToken: 'signed-qr-token',
          location: location,
          faceImageBase64: base64Encode(Uint8List.fromList([1, 2, 3])),
          faceChallenge: challenge['challenge_type'] as String,
          faceChallengeResponse: {'action': challenge['challenge_type']},
        );
    expect(faceAttempt['result'], 'accepted');
    final faceBody = backend.lastJsonBody('/bookings/9/check-in')!;
    expect(faceBody['face_image'], isNotNull);
    expect(faceBody['face_challenge'], 'turn');
    expect(faceBody['face_challenge_response'], {'action': 'turn'});

    expect(storage.token, 'test-token');
  });

  test('penolakan check-in memakai alasan terstruktur dari server', () async {
    backend.rejectCheckIn = true;
    final attempt = await container
        .read(checkInRepositoryProvider)
        .checkIn(
          bookingId: '9',
          qrToken: 'signed-qr-token',
          location: LocationEvidence(
            latitude: 0.5,
            longitude: 101.4,
            accuracy: 10,
            timestamp: DateTime.utc(2026, 11, 17, 2),
            isMock: false,
          ),
        );

    expect(attempt['result'], 'rejected');
    expect(
      checkInDecisionMessage(attempt),
      contains('di luar area yang diizinkan'),
    );
  });

  test('401 membersihkan token dan memicu sesi kedaluwarsa', () async {
    storage.token = 'expired-token';
    backend.unauthorizedOnMe = true;

    await expectLater(
      container.read(authRepositoryProvider).me(),
      throwsA(
        isA<ApiError>().having(
          (e) => e.message,
          'message',
          contains('Unauthenticated'),
        ),
      ),
    );

    expect(storage.token, isNull);
    expect(container.read(sessionExpiryProvider), 1);
  });
}
