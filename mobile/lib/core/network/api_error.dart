import 'package:dio/dio.dart';

class ApiError {
  const ApiError({required this.message, this.code, this.details});

  final String message;
  final String? code;
  final Object? details;

  factory ApiError.fromResponse(Response<Object?> response) {
    final data = response.data;
    if (data is Map<String, dynamic>) {
      return ApiError(
        message: data['message'] as String? ?? 'Request failed.',
        code: data['code'] as String?,
        details: data['details'],
      );
    }
    return const ApiError(message: 'Request failed.');
  }
}

ApiError mapDioError(Object error) {
  if (error is DioException && error.response != null) {
    return ApiError.fromResponse(error.response!);
  }
  return const ApiError(message: 'Network error. Check your connection.');
}
