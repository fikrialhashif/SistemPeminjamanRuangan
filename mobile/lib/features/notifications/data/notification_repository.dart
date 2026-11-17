import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_error.dart';
import '../../../core/network/dio_provider.dart';

final notificationRepositoryProvider = Provider<NotificationRepository>(
  (ref) => NotificationRepository(ref.read(dioProvider)),
);

class NotificationRepository {
  NotificationRepository(this._dio);
  final Dio _dio;

  Future<List<Map<String, dynamic>>> list() async {
    try {
      final response = await _dio.get('/notifications');
      final body = Map<String, dynamic>.from(response.data as Map);
      return (body['data'] as List? ?? [])
          .map((item) => Map<String, dynamic>.from(item as Map))
          .toList();
    } catch (error) {
      throw mapDioError(error);
    }
  }

  Future<List<Map<String, dynamic>>> listStrikes() async {
    try {
      final response = await _dio.get('/me/strikes');
      return (response.data as List)
          .map((item) => Map<String, dynamic>.from(item as Map))
          .toList();
    } catch (error) {
      throw mapDioError(error);
    }
  }

  Future<void> registerToken({
    required String token,
    required String platform,
  }) async {
    try {
      await _dio.post(
        '/notifications/device-token',
        data: {'token': token, 'platform': platform},
      );
    } catch (error) {
      throw mapDioError(error);
    }
  }
}
