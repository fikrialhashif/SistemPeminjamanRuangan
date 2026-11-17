import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../data/approval_repository.dart';

final pendingApprovalsProvider = FutureProvider<List<Map<String, dynamic>>>(
  (ref) => ref.read(approvalRepositoryProvider).pending(),
);
