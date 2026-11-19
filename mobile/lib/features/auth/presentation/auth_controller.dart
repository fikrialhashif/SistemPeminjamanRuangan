import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_error.dart';
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
  @override
  Future<Map<String, dynamic>?> build() async => null;

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
