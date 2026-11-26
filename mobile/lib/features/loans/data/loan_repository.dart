import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_error.dart';
import '../../../core/network/dio_provider.dart';
import '../../../core/network/pagination.dart';

final loanRepositoryProvider = Provider<LoanRepository>(
  (ref) => LoanRepository(ref.read(dioProvider)),
);

class LoanRepository {
  LoanRepository(this._dio);
  final Dio _dio;

  Future<List<Map<String, dynamic>>> list({String? status}) async {
    try {
      return await fetchAllPages(_dio, '/loans', query: {'status': ?status});
    } catch (error) {
      throw mapDioError(error);
    }
  }

  Future<Map<String, dynamic>> handover({
    required String loanId,
    required String assetToken,
    required String faceImage,
    required double latitude,
    required double longitude,
    required double accuracy,
    required DateTime gpsTimestamp,
    required bool gpsIsMock,
  }) async {
    try {
      final response = await _dio.post(
        '/loans/$loanId/handover',
        data: {
          'asset_token': assetToken,
          'face_image': faceImage,
          'gps_latitude': latitude,
          'gps_longitude': longitude,
          'gps_accuracy': accuracy,
          'gps_timestamp': gpsTimestamp.toUtc().toIso8601String(),
          'gps_is_mock': gpsIsMock,
        },
      );
      return Map<String, dynamic>.from(response.data as Map);
    } catch (error) {
      throw mapDioError(error);
    }
  }

  Future<Map<String, dynamic>> returnAsset({
    required String loanId,
    required String condition,
    String? assetToken,
    String? notes,
    String? photoPath,
  }) async {
    try {
      final form = FormData.fromMap({
        'condition': condition,
        if (assetToken != null && assetToken.isNotEmpty)
          'asset_token': assetToken,
        if (notes != null && notes.isNotEmpty) 'notes': notes,
        if (photoPath != null) 'photo': await MultipartFile.fromFile(photoPath),
      });
      final response = await _dio.post('/loans/$loanId/return', data: form);
      return Map<String, dynamic>.from(response.data as Map);
    } catch (error) {
      throw mapDioError(error);
    }
  }

  Future<Map<String, dynamic>> overrideVerification({
    required String loanId,
    required String reason,
  }) async {
    try {
      final response = await _dio.post(
        '/loans/$loanId/override',
        data: {'reason': reason},
      );
      return Map<String, dynamic>.from(response.data as Map);
    } catch (error) {
      throw mapDioError(error);
    }
  }

  Future<Map<String, dynamic>> reportDamage({
    required String loanId,
    required String notes,
    String? photoPath,
  }) async {
    try {
      final form = FormData.fromMap({
        'notes': notes,
        if (photoPath != null) 'photo': await MultipartFile.fromFile(photoPath),
      });
      final response = await _dio.post('/loans/$loanId/damage', data: form);
      return Map<String, dynamic>.from(response.data as Map);
    } catch (error) {
      throw mapDioError(error);
    }
  }
}
