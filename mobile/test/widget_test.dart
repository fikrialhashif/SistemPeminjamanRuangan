import 'package:flutter_test/flutter_test.dart';

import 'package:mobile/app.dart';

void main() {
  testWidgets('shows application shell', (tester) async {
    await tester.pumpWidget(const BookingApp());
    expect(find.text('Smart Room Booking'), findsOneWidget);
  });
}
