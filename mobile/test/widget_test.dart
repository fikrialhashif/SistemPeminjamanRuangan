import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'package:mobile/app.dart';

void main() {
  testWidgets('shows login shell', (tester) async {
    await tester.pumpWidget(const ProviderScope(child: BookingApp()));
    expect(find.text('Masuk'), findsOneWidget);
  });
}
