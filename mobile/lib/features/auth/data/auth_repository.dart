import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_error.dart';
import '../../../core/network/dio_provider.dart';
import '../../../core/storage/token_storage.dart';

final authRepositoryProvider = Provider<AuthRepository>(
  (ref) =>
      AuthRepository(ref.read(dioProvider), ref.read(tokenStorageProvider)),
);

class AuthRepository {
  AuthRepository(this._dio, this._storage);

  final Dio _dio;
  final TokenStorage _storage;

  Future<Map<String, dynamic>> login(String email, String password) async {
    try {
      final response = await _dio.post(
        '/auth/login',
        data: {'email': email, 'password': password},
      );
      final data = Map<String, dynamic>.from(response.data as Map);
      await _storage.write(data['token'] as String);
      return data;
    } catch (error) {
      throw mapDioError(error);
    }
  }

  Future<void> logout() async {
    try {
      await _dio.post('/auth/logout');
    } finally {
      await _storage.clear();
    }
  }

  Future<Map<String, dynamic>> me() async {
    try {
      final response = await _dio.get('/me');
      return Map<String, dynamic>.from(response.data as Map);
    } catch (error) {
      throw mapDioError(error);
    }
  }

  Future<Map<String, dynamic>> updateProfile(String name) async {
    try {
      final response = await _dio.put('/me/profile', data: {'name': name});
      return Map<String, dynamic>.from(response.data as Map);
    } catch (error) {
      throw mapDioError(error);
    }
  }

  Future<void> updatePassword({
    required String currentPassword,
    required String newPassword,
    required String confirmation,
  }) async {
    try {
      await _dio.put(
        '/me/password',
        data: {
          'current_password': currentPassword,
          'password': newPassword,
          'password_confirmation': confirmation,
        },
      );
    } catch (error) {
      throw mapDioError(error);
    }
  }
}
