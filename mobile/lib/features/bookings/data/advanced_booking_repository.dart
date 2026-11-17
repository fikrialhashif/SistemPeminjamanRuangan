import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_error.dart';
import '../../../core/network/dio_provider.dart';

final advancedBookingRepositoryProvider = Provider<AdvancedBookingRepository>(
  (ref) => AdvancedBookingRepository(ref.read(dioProvider)),
);

class AdvancedBookingRepository {
  AdvancedBookingRepository(this._dio);
  final Dio _dio;

  Future<Map<String, dynamic>> bundled(Map<String, dynamic> data) async {
    try {
      final response = await _dio.post('/bookings/bundled', data: data);
      return Map<String, dynamic>.from(response.data as Map);
    } catch (error) {
      throw mapDioError(error);
    }
  }

  Future<Map<String, dynamic>> recurring(Map<String, dynamic> data) async {
    try {
      final response = await _dio.post('/bookings/recurring', data: data);
      return Map<String, dynamic>.from(response.data as Map);
    } catch (error) {
      throw mapDioError(error);
    }
  }

  Future<Map<String, dynamic>> joinWaitlist(String bookingId) async {
    try {
      final response = await _dio.post('/bookings/$bookingId/waitlist');
      return Map<String, dynamic>.from(response.data as Map);
    } catch (error) {
      throw mapDioError(error);
    }
  }
}
