import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/core/network/api_error.dart';
import 'package:mobile/core/network/dio_provider.dart';
import 'package:mobile/features/approvals/data/approval_repository.dart';
import 'package:mobile/features/bookings/data/booking_repository.dart';
import 'package:mobile/features/loans/data/loan_repository.dart';
import 'package:mobile/features/notifications/data/notification_repository.dart';
import 'package:mobile/features/resources/data/resource_repository.dart';

import '../support/fake_backend.dart';

/// Answers like a Laravel paginator holding three pages of two items.
class PagedBackend implements HttpClientAdapter {
  PagedBackend({this.failOnPage});

  final int? failOnPage;
  final List<RequestOptions> requests = [];

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    requests.add(options);
    final page = int.parse('${options.queryParameters['page'] ?? 1}');
    return ResponseBody.fromString(
      jsonEncode({
        'data': [
          {'id': page * 2 - 1},
          {'id': page * 2},
        ],
        'next_page_url': page < 3 ? 'http://x/api?page=${page + 1}' : null,
      }),
      page == failOnPage ? 500 : 200,
      headers: {
        Headers.contentTypeHeader: [Headers.jsonContentType],
      },
    );
  }

  @override
  void close({bool force = false}) {}
}

ProviderContainer containerFor(PagedBackend backend) {
  final container = ProviderContainer(
    overrides: [tokenStorageProvider.overrideWithValue(FakeTokenStorage())],
  );
  addTearDown(container.dispose);
  container.read(dioProvider).httpClientAdapter = backend;
  return container;
}

void main() {
  final lists =
      <String, Future<List<Map<String, dynamic>>> Function(ProviderContainer)>{
        'resource': (c) => c.read(resourceRepositoryProvider).list(),
        'booking': (c) => c.read(bookingRepositoryProvider).list(),
        'persetujuan': (c) => c.read(approvalRepositoryProvider).pending(),
        'peminjaman': (c) => c.read(loanRepositoryProvider).list(),
        'notifikasi': (c) => c.read(notificationRepositoryProvider).list(),
      };

  for (final entry in lists.entries) {
    test('daftar ${entry.key} memuat semua halaman', () async {
      final backend = PagedBackend();

      final items = await entry.value(containerFor(backend));

      expect(items.map((item) => item['id']), [1, 2, 3, 4, 5, 6]);
      expect(backend.requests, hasLength(3));
    });
  }

  test('filter tetap dikirim di setiap halaman', () async {
    final backend = PagedBackend();

    await containerFor(backend)
        .read(resourceRepositoryProvider)
        .list(type: 'room');

    expect(backend.requests.map((request) => request.queryParameters['type']), [
      'room',
      'room',
      'room',
    ]);
  });

  test('kegagalan di halaman berikutnya dilaporkan sebagai ApiError', () async {
    final container = containerFor(PagedBackend(failOnPage: 2));

    expect(
      container.read(bookingRepositoryProvider).list(),
      throwsA(isA<ApiError>()),
    );
  });
}
