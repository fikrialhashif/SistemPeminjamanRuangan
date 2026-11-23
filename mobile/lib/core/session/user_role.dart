abstract final class UserRole {
  static const approver = 'approver';
  static const admin = 'admin';
  static const resourceManager = 'resource_manager';

  static bool shouldShowApprovals(Object? user) {
    if (user is! Map) return false;

    final roles = user['roles'];
    if (roles is List) {
      for (final role in roles) {
        final name = role is Map ? role['name']?.toString() : role?.toString();
        if (name == approver || name == admin) return true;
      }
    }

    final role = user['role']?.toString();
    return role == approver || role == admin;
  }

  static bool shouldShowLoans(Object? user) {
    if (user is! Map) return false;

    final roles = user['roles'];
    if (roles is List) {
      for (final role in roles) {
        final name = role is Map ? role['name']?.toString() : role?.toString();
        if (name == resourceManager || name == admin) return true;
      }
    }

    final role = user['role']?.toString();
    return role == resourceManager || role == admin;
  }
}
