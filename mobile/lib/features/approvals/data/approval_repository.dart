import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_error.dart';
import '../../../core/network/dio_provider.dart';

final approvalRepositoryProvider = Provider<ApprovalRepository>(
  (ref) => ApprovalRepository(ref.read(dioProvider)),
);

class ApprovalRepository {
  ApprovalRepository(this._dio);
  final Dio _dio;

  Future<List<Map<String, dynamic>>> pending() async {
    try {
      final response = await _dio.get('/approvals/pending');
      final data = (response.data as Map)['data'] as List? ?? const [];
      return data
          .map((item) => Map<String, dynamic>.from(item as Map))
          .toList();
    } catch (error) {
      throw mapDioError(error);
    }
  }

  Future<void> decide({
    required String id,
    required bool approve,
    String? notes,
  }) async {
    try {
      await _dio.post(
        '/approvals/$id/${approve ? 'approve' : 'reject'}',
        data: {if (notes?.trim().isNotEmpty == true) 'notes': notes!.trim()},
      );
    } catch (error) {
      throw mapDioError(error);
    }
  }
}
