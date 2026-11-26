import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_error.dart';
import '../../../core/network/dio_provider.dart';
import '../../../core/network/pagination.dart';

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
      return await fetchAllPages(
        _dio,
        '/resources',
        query: {
          if (search?.isNotEmpty == true) 'search': search,
          if (type?.isNotEmpty == true) 'type': type,
          if (category?.isNotEmpty == true) 'category': category,
          if (locationId?.isNotEmpty == true) 'location_id': locationId,
          'capacity_min': ?capacityMin,
        },
      );
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
