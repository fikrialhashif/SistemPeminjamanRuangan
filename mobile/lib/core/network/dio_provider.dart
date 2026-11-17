import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../config/app_environment.dart';
import '../session/session_expiry.dart';
import '../storage/token_storage.dart';

final tokenStorageProvider = Provider<TokenStorage>(
  (ref) => const TokenStorage(),
);

final dioProvider = Provider<Dio>((ref) {
  final dio = Dio(
    BaseOptions(
      baseUrl: AppEnvironment.apiBaseUrl,
      headers: {'Accept': 'application/json'},
      connectTimeout: const Duration(seconds: 20),
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
