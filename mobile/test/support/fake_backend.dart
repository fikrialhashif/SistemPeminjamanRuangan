import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:mobile/core/storage/token_storage.dart';

class FakeTokenStorage extends TokenStorage {
  String? token;

  @override
  Future<String?> read() async => token;

  @override
  Future<void> write(String value) async => token = value;

  @override
  Future<void> clear() async => token = null;
}

class FakeBackend implements HttpClientAdapter {
  final List<RequestOptions> requests = [];
  final List<String> hosts = <String>[];
  final Set<String> failHosts = <String>{};
  bool rejectCheckIn = false;
  bool unauthorizedOnMe = false;
  int bookingId = 9;

  Map<String, dynamic>? lastJsonBody(String pathSuffix) {
    for (final request in requests.reversed) {
      if (request.uri.path.endsWith(pathSuffix)) {
        final data = request.data;
        return data is Map ? Map<String, dynamic>.from(data) : null;
      }
    }
    return null;
  }

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    requests.add(options);
    hosts.add(options.uri.host);
    if (failHosts.contains(options.uri.host)) {
      throw DioException(
        requestOptions: options,
        type: DioExceptionType.connectionError,
        error: 'Connection refused',
      );
    }
    final path = options.uri.path;

    if (path.endsWith('/auth/login') && options.method == 'POST') {
      return _json({
        'token': 'test-token',
        'user': {'id': 1, 'name': 'Budi', 'role': 'user'},
      });
    }
    if (path.endsWith('/auth/logout')) {
      return _json({'message': 'Logged out.'});
    }
    if (path.endsWith('/me')) {
      if (unauthorizedOnMe) {
        return _json({'message': 'Unauthenticated.'}, status: 401);
      }
      return _json({'id': 1, 'name': 'Budi', 'role': 'user'});
    }
    if (path.endsWith('/resources') && options.method == 'GET') {
      return _json({
        'data': [
          {
            'id': 1,
            'name': 'Ruang Rapat A',
            'type': 'room',
            'capacity': 10,
            'location': {'name': 'Gedung Utama'},
          },
        ],
      });
    }
    if (path.contains('/resources/1/availability')) {
      return _json({
        'slots': [
          {
            'starts_at': '2026-11-17T02:00:00Z',
            'ends_at': '2026-11-17T03:00:00Z',
            'available': true,
          },
        ],
      });
    }
    if (path.endsWith('/bookings') && options.method == 'POST') {
      return _json({
        'id': bookingId,
        'status': 'approved',
        'resource_id': 1,
        'starts_at': '2026-11-17T02:00:00Z',
        'ends_at': '2026-11-17T03:00:00Z',
      }, status: 201);
    }
    if (path.endsWith('/bookings') && options.method == 'GET') {
      return _json({
        'data': [
          {
            'id': bookingId,
            'status': 'approved',
            'title': 'Rapat tim',
            'starts_at': '2026-11-17T02:00:00Z',
            'resource': {'name': 'Ruang Rapat A'},
          },
        ],
      });
    }
    if (path.endsWith('/bookings/$bookingId') && options.method == 'GET') {
      final accepted = !rejectCheckIn;
      return _json({
        'id': bookingId,
        'status': accepted ? 'checked_in' : 'approved',
        'title': 'Rapat tim',
        'starts_at': '2026-11-17T02:00:00Z',
        'ends_at': '2026-11-17T03:00:00Z',
        'resource': {
          'name': 'Ruang Rapat A',
          'location': {'name': 'Gedung Utama'},
        },
        'check_in_attempts': [
          {
            'id': 1,
            'result': accepted ? 'accepted' : 'rejected',
            'qr_valid': true,
            'gps_valid': true,
            'face_valid': accepted,
            'face_score': 0.91,
            'created_at': '2026-11-17T02:05:00Z',
            'rejection_reasons': accepted
                ? null
                : ['gps: Distance 250.0m exceeds geofence radius of 100m'],
          },
        ],
      });
    }
    if (path.endsWith('/bookings/$bookingId/check-in') &&
        options.method == 'POST') {
      if (rejectCheckIn) {
        return _json({
          'result': 'rejected',
          'rejection_reasons': [
            'gps: Distance 250.0m exceeds geofence radius of 100m',
          ],
        });
      }
      return _json({
        'result': 'accepted',
        'rejection_reasons': null,
        'qr_valid': true,
        'gps_valid': true,
        'face_valid': true,
      });
    }
    if (path.endsWith('/face/enroll')) {
      return _json({'status': 'enrolled'}, status: 201);
    }
    if (path.endsWith('/face/status')) {
      return _json({'enrolled': true, 'enabled': true});
    }
    if (path.endsWith('/face/challenge')) {
      return _json({
        'challenge_type': 'turn',
        'instruction': 'Tolehkan kepala ke kiri atau kanan',
      });
    }
    if (path.endsWith('/face') && options.method == 'DELETE') {
      return _json({'message': 'Face data deleted.'});
    }
    if (path.endsWith('/notifications')) {
      return _json({'data': <Object>[]});
    }
    if (path.endsWith('/me/strikes')) {
      return _json(<Object>[]);
    }
    return _json({'message': 'Not found.'}, status: 404);
  }

  ResponseBody _json(Object body, {int status = 200}) =>
      ResponseBody.fromString(
        jsonEncode(body),
        status,
        headers: {
          Headers.contentTypeHeader: [Headers.jsonContentType],
        },
      );

  @override
  void close({bool force = false}) {}
}
