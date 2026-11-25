import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:mobile/core/storage/token_storage.dart';
import 'package:mobile/features/onboarding/data/onboarding_storage.dart';

class FakeTokenStorage extends TokenStorage {
  String? token;

  @override
  Future<String?> read() async => token;

  @override
  Future<void> write(String value) async => token = value;

  @override
  Future<void> clear() async => token = null;
}

class FakeOnboardingStorage extends OnboardingStorage {
  FakeOnboardingStorage({this.completed = false});

  bool completed;

  @override
  Future<bool> isCompleted() async => completed;

  @override
  Future<void> markCompleted() async => completed = true;
}

class FakeBackend implements HttpClientAdapter {
  final List<RequestOptions> requests = [];
  final List<String> hosts = <String>[];
  final Set<String> failHosts = <String>{};
  bool rejectCheckIn = false;
  String? rejectCheckInReason;
  bool unauthorizedOnMe = false;
  List<String>? loginRoleNames;
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
      final roles = (loginRoleNames ?? const <String>[])
          .map((name) => {'id': 1, 'name': name})
          .toList();
      return _json({
        'token': 'test-token',
        'user': {'id': 1, 'name': 'Budi', 'role': 'user', 'roles': roles},
      });
    }
    if (path.endsWith('/auth/logout')) {
      return _json({'message': 'Logged out.'});
    }
    if (path.endsWith('/approvals/pending')) {
      return _json({
        'data': [
          {
            'id': 3,
            'booking': {
              'id': 9,
              'starts_at': '2026-11-17T02:00:00Z',
              'resource': {'name': 'Ruang Rapat A'},
              'user': {'name': 'Siti'},
            },
          },
        ],
        'total': 1,
      });
    }
    if (path.contains('/approvals/') && options.method == 'POST') {
      return _json({'message': 'Keputusan tersimpan.'});
    }
    if (path.endsWith('/me/profile') && options.method == 'PUT') {
      final data = options.data is Map ? options.data as Map : const {};
      return _json({
        'id': 1,
        'name': data['name'],
        'email': 'budi@example.com',
        'roles': <Object>[],
        'department': null,
      });
    }
    if (path.endsWith('/me/password') && options.method == 'PUT') {
      return _json({'message': 'Password berhasil diperbarui.'});
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
        'date': '2026-11-17',
        'open_time': '07:00',
        'close_time': '22:00',
        'hours_source': 'room',
        'intervals': [
          {
            'start': '2026-11-16T19:00:00Z',
            'end': '2026-11-16T20:00:00Z',
            'available': true,
            'status': 'free',
            'status_detail': null,
            'label': 'Tersedia',
          },
          {
            'start': '2026-11-16T20:00:00Z',
            'end': '2026-11-16T21:00:00Z',
            'available': false,
            'status': 'booked',
            'status_detail': 'approved',
            'label': 'Dipesan',
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
            rejectCheckInReason ??
                'gps: Distance 250.0m exceeds geofence radius of 100m',
          ],
          'verification': {
            'subject_type': 'booking',
            'subject_id': 9,
            'accepted': false,
            'steps': [
              {'stage': 'location', 'passed': true},
              {'stage': 'qr', 'passed': true},
              {'stage': 'face', 'passed': true},
              {'stage': 'time', 'passed': false},
            ],
          },
        });
      }
      return _json({
        'result': 'accepted',
        'rejection_reasons': null,
        'qr_valid': true,
        'gps_valid': true,
        'face_valid': true,
        'verification': {
          'subject_type': 'booking',
          'subject_id': 9,
          'accepted': true,
          'steps': [
            {'stage': 'location', 'passed': true},
            {'stage': 'qr', 'passed': true},
            {'stage': 'face', 'passed': true},
            {'stage': 'time', 'passed': true},
          ],
        },
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
    if (path.contains('/loans/') &&
        path.endsWith('/handover') &&
        options.method == 'POST') {
      return _json({'id': 7, 'status': 'handed_over'});
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
