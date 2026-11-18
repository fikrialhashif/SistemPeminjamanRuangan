import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/features/check_in/presentation/check_in_page.dart';

void main() {
  testWidgets('menampilkan tiga tahap konfirmasi booking', (tester) async {
    await tester.pumpWidget(
      const ProviderScope(
        child: MaterialApp(home: CheckInPage(bookingId: '9')),
      ),
    );

    expect(find.text('Lokasi'), findsOneWidget);
    expect(find.text('QR'), findsOneWidget);
    expect(find.text('Wajah'), findsOneWidget);
    expect(find.text('Langkah 1 - Lokasi'), findsOneWidget);
    expect(find.text('Ambil lokasi'), findsOneWidget);

    final next = tester.widget<FilledButton>(
      find.widgetWithText(FilledButton, 'Lanjut'),
    );
    expect(next.onPressed, isNull);
  });
}
