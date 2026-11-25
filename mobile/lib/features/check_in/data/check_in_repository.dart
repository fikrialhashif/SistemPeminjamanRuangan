import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_error.dart';
import '../../../core/network/dio_provider.dart';
import 'location_service.dart';

final checkInRepositoryProvider = Provider<CheckInRepository>(
  (ref) => CheckInRepository(ref.read(dioProvider)),
);

class CheckInRepository {
  CheckInRepository(this._dio);
  final Dio _dio;

  Future<Map<String, dynamic>> checkIn({
    required String bookingId,
    required String qrToken,
    required LocationEvidence location,
    String? faceImageBase64,
    String? faceFrontImageBase64,
    String? faceChallenge,
    Map<String, dynamic>? faceChallengeResponse,
  }) async {
    try {
      final response = await _dio.post(
        '/bookings/$bookingId/check-in',
        data: {
          'qr_token': qrToken,
          ...location.toJson(),
          'face_image': ?faceImageBase64,
          'face_front_image': ?faceFrontImageBase64,
          'face_challenge': ?faceChallenge,
          'face_challenge_response': ?faceChallengeResponse,
        },
      );
      return Map<String, dynamic>.from(response.data as Map);
    } catch (error) {
      throw mapDioError(error);
    }
  }
}

String checkInReasonMessage(String? code) =>
    const {
      'outside_geofence': 'Anda berada di luar area yang diizinkan.',
      'inaccurate_location':
          'Akurasi lokasi belum cukup. Coba pindah ke area terbuka.',
      'stale_location': 'Lokasi sudah kedaluwarsa. Ambil lokasi ulang.',
      'mock_location': 'Lokasi tiruan terdeteksi.',
      'invalid_qr': 'QR pintu tidak valid. Pastikan sesuai booking.',
      'wrong_room': 'QR bukan untuk ruangan booking ini.',
      'outside_window': 'Belum masuk waktu check-in.',
      'face_not_enrolled':
          'Wajah belum terdaftar. Daftarkan wajah terlebih dahulu.',
      'face_liveness_failed':
          'Verifikasi liveness gagal. Ulangi gerakan yang diminta.',
    }[code] ??
    'Check-in ditolak. Ikuti petunjuk lalu coba lagi.';

bool isFaceIdentityMismatch(Map<String, dynamic> result) {
  final reasons = result['rejection_reasons'];
  if (reasons is! List) return false;
  return reasons.any((reason) {
    final value = reason.toString().toLowerCase();
    return value.contains('verification failed') || value.contains('no match');
  });
}

String checkInDecisionMessage(Map<String, dynamic> result) {
  final state = result['result']?.toString() ?? result['status']?.toString();
  if (state == 'accepted') return 'Check-in berhasil.';
  if (state == 'needs_manual_review') {
    return 'Verifikasi manual diperlukan. Tunjukkan hasil ini kepada petugas.';
  }

  final structured = result['reason_code']?.toString();
  if (structured != null) return checkInReasonMessage(structured);

  final rawReasons = result['rejection_reasons'];
  if (rawReasons is List && rawReasons.isNotEmpty) {
    final messages = <String>{};
    for (final raw in rawReasons) {
      messages.add(_reasonFromServer(raw.toString()));
    }
    if (messages.isNotEmpty) return messages.join('\n');
  }
  return 'Check-in ditolak. Ikuti petunjuk lalu coba lagi.';
}

String _reasonFromServer(String reason) {
  final value = reason.toLowerCase();
  if (value.contains('waktu') || value.contains('window')) {
    return 'Waktu check-in di luar rentang yang diizinkan.';
  }
  if (value.contains('qr:') || value.contains('token')) {
    if (value.contains('mismatch')) {
      return 'QR bukan untuk ruangan booking ini.';
    }
    return 'QR tidak valid. Pastikan QR pintu sesuai booking.';
  }
  if (value.contains('mock')) {
    return 'Lokasi tiruan terdeteksi. Matikan aplikasi mock-location.';
  }
  if (value.contains('accuracy')) {
    return 'Akurasi lokasi belum cukup. Coba pindah ke area terbuka.';
  }
  if (value.contains('stale')) {
    return 'Lokasi sudah kedaluwarsa. Ambil lokasi ulang.';
  }
  if (value.contains('geofence') || value.contains('distance')) {
    return 'Anda berada di luar area yang diizinkan.';
  }
  if (value.contains('velocity')) {
    return 'Perpindahan lokasi tidak wajar. Tunggu lalu coba lagi.';
  }
  if (value.contains('face')) {
    if (value.contains('not provided')) {
      return 'Wajah belum terdeteksi. Ulangi verifikasi wajah.';
    }
    if (value.contains('not enrolled')) {
      return 'Wajah belum terdaftar. Daftarkan wajah terlebih dahulu.';
    }
    if (value.contains('liveness')) {
      return 'Liveness gagal. Ulangi gerakan yang diminta.';
    }
    if (value.contains('unavailable')) {
      return 'Layanan wajah sedang tidak tersedia. Verifikasi manual diperlukan.';
    }
    if (value.contains('verification failed')) {
      return 'Wajah tidak sesuai dengan data wajah yang terdaftar.';
    }
    return 'Verifikasi wajah gagal. Posisikan wajah di dalam bingkai.';
  }
  return 'Check-in ditolak. Ikuti petunjuk lalu coba lagi.';
}
