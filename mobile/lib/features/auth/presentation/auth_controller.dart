import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_error.dart';
import '../../../core/network/dio_provider.dart';
import '../../../core/session/user_role.dart';
import '../data/auth_repository.dart';

final approvalAccessProvider = Provider<bool>((ref) {
  final user = ref.watch(authControllerProvider).value?['user'];
  return UserRole.shouldShowApprovals(user);
});

final authControllerProvider =
    AsyncNotifierProvider<AuthController, Map<String, dynamic>?>(
      AuthController.new,
    );

class AuthController extends AsyncNotifier<Map<String, dynamic>?> {
  /// Resumes the session behind a stored token, so reopening the app does not
  /// ask for a new login.
  @override
  Future<Map<String, dynamic>?> build() async {
    try {
      final token = await ref.read(tokenStorageProvider).read();
      if (token == null) return null;
      final user = await ref.read(authRepositoryProvider).me();
      return {'token': token, 'user': user};
    } catch (_) {
      // Unreadable storage, a rejected token or no network all end at login.
      // Only a rejected token is removed, by the 401 interceptor.
      return null;
    }
  }

  /// Forgets the signed-in user after the server ended the session.
  void clearSession() => state = const AsyncData(null);

  Future<void> login(String email, String password) async {
    state = const AsyncLoading();
    state = await AsyncValue.guard(
      () => ref.read(authRepositoryProvider).login(email, password),
    );
  }

  Future<void> logout() async {
    state = const AsyncLoading();
    state = await AsyncValue.guard(() async {
      await ref.read(authRepositoryProvider).logout();
      return null;
    });
  }

  Future<Map<String, dynamic>> updateProfile(String name) async {
    final user = await ref.read(authRepositoryProvider).updateProfile(name);
    final current = state.value;
    if (current != null) {
      state = AsyncData<Map<String, dynamic>?>({...current, 'user': user});
    }
    return user;
  }

  Future<void> updatePassword({
    required String currentPassword,
    required String newPassword,
    required String confirmation,
  }) {
    return ref
        .read(authRepositoryProvider)
        .updatePassword(
          currentPassword: currentPassword,
          newPassword: newPassword,
          confirmation: confirmation,
        );
  }

  String? errorMessage() => state.error is ApiError
      ? (state.error! as ApiError).message
      : state.error?.toString();
}
