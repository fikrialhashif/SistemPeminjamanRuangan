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
