import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'face_repository.dart';

final faceStatusProvider = FutureProvider.autoDispose<Map<String, dynamic>>(
  (ref) => ref.read(faceRepositoryProvider).status(),
);
