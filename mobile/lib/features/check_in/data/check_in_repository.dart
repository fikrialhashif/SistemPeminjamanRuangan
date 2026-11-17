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
  }) async {
    try {
      final response = await _dio.post(
        '/bookings/$bookingId/check-in',
        data: {'qr_token': qrToken, ...location.toJson()},
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
      'invalid_qr': 'QR tidak valid atau sudah kedaluwarsa.',
      'wrong_room': 'QR bukan untuk ruangan booking ini.',
      'outside_window': 'Belum masuk waktu check-in.',
    }[code] ??
    'Check-in ditolak. Ikuti petunjuk lalu coba lagi.';

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
  if (value.contains('qr:') || value.contains('token')) {
    if (value.contains('expired')) {
      return 'QR sudah kedaluwarsa. Pindai ulang QR terbaru.';
    }
    if (value.contains('already used')) {
      return 'QR sudah pernah dipakai. Pindai QR terbaru.';
    }
    if (value.contains('mismatch')) {
      return 'QR bukan untuk ruangan booking ini.';
    }
    return 'QR tidak valid. Pindai ulang QR di ruangan.';
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
    return 'Verifikasi wajah gagal. Posisikan wajah di dalam bingkai.';
  }
  return 'Check-in ditolak. Ikuti petunjuk lalu coba lagi.';
}
