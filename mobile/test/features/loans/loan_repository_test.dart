import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/core/network/dio_provider.dart';
import 'package:mobile/features/loans/data/loan_repository.dart';

import '../../support/fake_backend.dart';

void main() {
  test('handover mengirim asset_token hasil pindai', () async {
    final backend = FakeBackend();
    final container = ProviderContainer(
      overrides: [tokenStorageProvider.overrideWithValue(FakeTokenStorage())],
    );
    addTearDown(container.dispose);
    container.read(dioProvider).httpClientAdapter = backend;

    await container
        .read(loanRepositoryProvider)
        .handover(
          loanId: '7',
          assetToken: 'signed-asset-token',
          faceImage: 'signed-face-base64',
          latitude: -6.2,
          longitude: 106.8,
          accuracy: 10,
          gpsTimestamp: DateTime.utc(2026, 11, 25, 6),
          gpsIsMock: false,
        );

    final body = backend.lastJsonBody('/loans/7/handover');
    expect(body?['asset_token'], 'signed-asset-token');
    expect(body?['face_image'], 'signed-face-base64');
    expect(body?['gps_latitude'], -6.2);
    expect(body?['gps_longitude'], 106.8);
    expect(body?['gps_accuracy'], 10);
    expect(body?['gps_timestamp'], '2026-11-25T06:00:00.000Z');
    expect(body?['gps_is_mock'], false);
    expect(body?.containsKey('asset_code'), false);
  });
}
