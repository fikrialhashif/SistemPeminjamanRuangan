import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_error.dart';
import '../../../core/network/dio_provider.dart';

final resourceRepositoryProvider = Provider<ResourceRepository>(
  (ref) => ResourceRepository(ref.read(dioProvider)),
);

class ResourceRepository {
  ResourceRepository(this._dio);
  final Dio _dio;

  Future<List<Map<String, dynamic>>> list({
    String? search,
    String? type,
    String? category,
    String? locationId,
    int? capacityMin,
  }) async {
    try {
      final response = await _dio.get(
        '/resources',
        queryParameters: {
          if (search?.isNotEmpty == true) 'search': search,
          if (type?.isNotEmpty == true) 'type': type,
          if (category?.isNotEmpty == true) 'category': category,
          if (locationId?.isNotEmpty == true) 'location_id': locationId,
          'capacity_min': ?capacityMin,
        },
      );
      final data = response.data is Map
          ? (response.data['data'] as List? ?? const [])
          : response.data as List;
      return data
          .map((item) => Map<String, dynamic>.from(item as Map))
          .toList();
    } catch (error) {
      throw mapDioError(error);
    }
  }

  Future<Map<String, dynamic>> get(String id) async {
    try {
      final response = await _dio.get('/resources/$id');
      return Map<String, dynamic>.from(response.data as Map);
    } catch (error) {
      throw mapDioError(error);
    }
  }
}
