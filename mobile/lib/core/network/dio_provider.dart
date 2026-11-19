import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../config/app_environment.dart';
import '../session/session_expiry.dart';
import '../storage/token_storage.dart';

final tokenStorageProvider = Provider<TokenStorage>(
  (ref) => const TokenStorage(),
);

final dioProvider = Provider<Dio>((ref) {
  final fallbackBaseUrls = AppEnvironment.connectionFallbackBaseUrls;
  final dio = Dio(
    BaseOptions(
      baseUrl: AppEnvironment.apiBaseUrl,
      headers: {'Accept': 'application/json'},
      connectTimeout: fallbackBaseUrls.isEmpty
          ? const Duration(seconds: 20)
          : const Duration(seconds: 5),
      receiveTimeout: const Duration(seconds: 20),
      sendTimeout: const Duration(seconds: 30),
    ),
  );
  dio.interceptors.add(
    InterceptorsWrapper(
      onRequest: (options, handler) async {
        final token = await ref.read(tokenStorageProvider).read();
        if (token != null) options.headers['Authorization'] = 'Bearer $token';
        handler.next(options);
      },
      onError: (error, handler) async {
        final nextBaseUrl = _nextFallbackBaseUrl(error, fallbackBaseUrls);
        if (nextBaseUrl != null) {
          final request = error.requestOptions;
          final rawTried = request.extra['triedBaseUrls'];
          final tried = rawTried is List
              ? List<String>.from(rawTried)
              : <String>[];
          tried.add(request.baseUrl);
          request.extra['triedBaseUrls'] = tried;
          request.baseUrl = nextBaseUrl;
          try {
            final response = await dio.fetch(request);
            dio.options.baseUrl = nextBaseUrl;
            handler.resolve(response);
            return;
          } on DioException catch (retryError) {
            if (retryError.response != null) {
              dio.options.baseUrl = nextBaseUrl;
            }
            handler.next(retryError);
            return;
          }
        }
        final isLogin = error.requestOptions.path.endsWith('/auth/login');
        if (error.response?.statusCode == 401 && !isLogin) {
          await ref.read(tokenStorageProvider).clear();
          ref.read(sessionExpiryProvider.notifier).notifyExpired();
        }
        handler.next(error);
      },
    ),
  );
  return dio;
});

String? _nextFallbackBaseUrl(DioException error, List<String> fallbacks) {
  if (fallbacks.isEmpty || error.response != null) return null;
  final type = error.type;
  final isConnectionIssue =
      type == DioExceptionType.connectionError ||
      type == DioExceptionType.connectionTimeout ||
      type == DioExceptionType.receiveTimeout ||
      type == DioExceptionType.sendTimeout;
  if (!isConnectionIssue) return null;
  final rawTried = error.requestOptions.extra['triedBaseUrls'];
  final tried = rawTried is List ? List<String>.from(rawTried) : <String>[];
  for (final baseUrl in fallbacks) {
    if (baseUrl != error.requestOptions.baseUrl && !tried.contains(baseUrl)) {
      return baseUrl;
    }
  }
  return null;
}
