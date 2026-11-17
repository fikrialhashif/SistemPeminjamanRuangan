import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_error.dart';
import '../../../core/network/dio_provider.dart';

final loanRepositoryProvider = Provider<LoanRepository>(
  (ref) => LoanRepository(ref.read(dioProvider)),
);

class LoanRepository {
  LoanRepository(this._dio);
  final Dio _dio;

  Future<List<Map<String, dynamic>>> list({String? status}) async {
    try {
      final response = await _dio.get(
        '/loans',
        queryParameters: status == null ? null : {'status': status},
      );
      final data = Map<String, dynamic>.from(response.data as Map);
      return (data['data'] as List? ?? [])
          .map((item) => Map<String, dynamic>.from(item as Map))
          .toList();
    } catch (error) {
      throw mapDioError(error);
    }
  }

  Future<Map<String, dynamic>> handover({
    required String loanId,
    required String assetCode,
    required String faceImage,
    required double latitude,
    required double longitude,
    required double accuracy,
  }) async {
    try {
      final response = await _dio.post(
        '/loans/$loanId/handover',
        data: {
          'asset_code': assetCode,
          'face_image': faceImage,
          'gps_latitude': latitude,
          'gps_longitude': longitude,
          'gps_accuracy': accuracy,
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
    String? notes,
    String? photoPath,
  }) async {
    try {
      final form = FormData.fromMap({
        'condition': condition,
        if (notes != null && notes.isNotEmpty) 'notes': notes,
        if (photoPath != null) 'photo': await MultipartFile.fromFile(photoPath),
      });
      final response = await _dio.post('/loans/$loanId/return', data: form);
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
