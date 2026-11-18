import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_error.dart';
import '../../../core/network/dio_provider.dart';

final bookingRepositoryProvider = Provider<BookingRepository>(
  (ref) => BookingRepository(ref.read(dioProvider)),
);

class BookingRepository {
  BookingRepository(this._dio);
  final Dio _dio;

  Future<List<Map<String, dynamic>>> availability(
    String resourceId,
    DateTime date,
  ) async {
    try {
      final response = await _dio.get(
        '/resources/$resourceId/availability',
        queryParameters: {'date': _date(date)},
      );
      final slots = (response.data as Map)['slots'] as List? ?? const [];
      return slots
          .map((item) => Map<String, dynamic>.from(item as Map))
          .toList();
    } catch (error) {
      throw mapDioError(error);
    }
  }

  Future<Map<String, dynamic>> create({
    required String resourceId,
    required DateTime startsAt,
    required DateTime endsAt,
    String? title,
    String? notes,
  }) async {
    try {
      final response = await _dio.post(
        '/bookings',
        data: {
          'resource_id': resourceId,
          'starts_at': startsAt.toUtc().toIso8601String(),
          'ends_at': endsAt.toUtc().toIso8601String(),
          if (title?.isNotEmpty == true) 'title': title,
          if (notes?.isNotEmpty == true) 'notes': notes,
        },
      );
      return Map<String, dynamic>.from(response.data as Map);
    } catch (error) {
      throw mapDioError(error);
    }
  }

  Future<List<Map<String, dynamic>>> list() async {
    try {
      final response = await _dio.get('/bookings');
      final data = (response.data as Map)['data'] as List? ?? const [];
      return data
          .map((item) => Map<String, dynamic>.from(item as Map))
          .toList();
    } catch (error) {
      throw mapDioError(error);
    }
  }

  Future<Map<String, dynamic>> show(String id) async {
    try {
      final response = await _dio.get('/bookings/$id');
      return Map<String, dynamic>.from(response.data as Map);
    } catch (error) {
      throw mapDioError(error);
    }
  }

  Future<void> cancel(String id) async {
    try {
      await _dio.delete('/bookings/$id');
    } catch (error) {
      throw mapDioError(error);
    }
  }

  String _date(DateTime date) =>
      '${date.year.toString().padLeft(4, '0')}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';
}
