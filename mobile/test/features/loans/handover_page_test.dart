import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/features/loans/presentation/manager_loans_page.dart';

void main() {
  testWidgets('serah-terima menampilkan empat tahap verifikasi', (
    tester,
  ) async {
    await tester.pumpWidget(
      const ProviderScope(
        child: MaterialApp(home: HandoverPage(loanId: '7')),
      ),
    );
    await tester.pump();

    expect(find.text('Lokasi'), findsOneWidget);
    expect(find.text('QR'), findsOneWidget);
    expect(find.text('Wajah'), findsOneWidget);
    expect(find.text('Waktu'), findsOneWidget);
    expect(find.text('Langkah 1 - Lokasi'), findsOneWidget);

    final next = tester.widget<FilledButton>(
      find.widgetWithText(FilledButton, 'Lanjut'),
    );
    expect(next.onPressed, isNull);
  });
}
