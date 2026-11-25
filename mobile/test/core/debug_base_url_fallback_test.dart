import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/core/network/dio_provider.dart';

import '../support/fake_backend.dart';

void main() {
  test('debug login memakai fallback emulator saat localhost gagal', () async {
    final backend = FakeBackend()..failHosts.add('127.0.0.1');
    final container = ProviderContainer(
      overrides: [tokenStorageProvider.overrideWithValue(FakeTokenStorage())],
    );
    addTearDown(container.dispose);
    final dio = container.read(dioProvider);
    dio.httpClientAdapter = backend;

    final response = await dio.post(
      '/auth/login',
      data: {'email': 'budi@example.com', 'password': 'secret'},
    );

    expect(response.statusCode, 200);
    expect(backend.hosts, ['127.0.0.1', '10.0.2.2']);
    expect(dio.options.baseUrl, 'http://10.0.2.2:8000/api/v1');
  });
}
