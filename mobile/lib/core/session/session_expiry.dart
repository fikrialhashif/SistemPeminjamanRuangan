import 'package:flutter_riverpod/flutter_riverpod.dart';

class SessionExpiryNotifier extends Notifier<int> {
  @override
  int build() => 0;

  void notifyExpired() => state = state + 1;
}

final sessionExpiryProvider = NotifierProvider<SessionExpiryNotifier, int>(
  SessionExpiryNotifier.new,
);
