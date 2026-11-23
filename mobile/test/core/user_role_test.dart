import 'package:mobile/core/session/user_role.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('menu peminjaman hanya untuk resource manager dan admin', () {
    expect(UserRole.shouldShowLoans({'role': 'member'}), isFalse);
    expect(
      UserRole.shouldShowLoans({
        'roles': [
          {'name': 'resource_manager'},
        ],
      }),
      isTrue,
    );
    expect(
      UserRole.shouldShowLoans({
        'roles': [
          {'name': 'admin'},
        ],
      }),
      isTrue,
    );
  });
}
