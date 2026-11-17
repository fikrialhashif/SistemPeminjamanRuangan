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
    if (data is List) {
      return ApiError(message: 'Request failed.', details: data);
    }
    return const ApiError(message: 'Request failed.');
  }

  @override
  String toString() => message;
}

ApiError mapDioError(Object error) {
  if (error is DioException) {
    if (error.response != null) return ApiError.fromResponse(error.response!);
    if (error.type == DioExceptionType.connectionTimeout ||
        error.type == DioExceptionType.receiveTimeout ||
        error.type == DioExceptionType.sendTimeout) {
      return const ApiError(
        message: 'Koneksi ke server terlalu lama. Coba lagi.',
      );
    }
    if (error.type == DioExceptionType.connectionError) {
      return const ApiError(
        message: 'Tidak dapat terhubung ke server. Periksa jaringan Anda.',
      );
    }
  }
  return const ApiError(message: 'Network error. Check your connection.');
}

String apiErrorMessage(Object? error) => error is ApiError
    ? error.message
    : (error?.toString() ?? 'Terjadi kesalahan.');
