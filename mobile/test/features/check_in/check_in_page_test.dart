import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/features/bookings/presentation/booking_providers.dart';
import 'package:mobile/features/check_in/presentation/check_in_page.dart';

void main() {
  testWidgets('menampilkan empat tahap konfirmasi booking', (tester) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          bookingDetailProvider('9').overrideWith(
            (ref) async => {
              'resource': {
                'location': {
                  'name': 'Gedung A',
                  'radius': 1000,
                  'latitude': 0.5,
                  'longitude': 101.4,
                  'polygon': null,
                },
              },
            },
          ),
        ],
        child: const MaterialApp(home: CheckInPage(bookingId: '9')),
      ),
    );
    await tester.pump();

    expect(find.text('Lokasi'), findsOneWidget);
    expect(find.text('QR'), findsOneWidget);
    expect(find.text('Wajah'), findsOneWidget);
    expect(find.text('Waktu'), findsOneWidget);
    expect(find.text('Langkah 1 - Lokasi'), findsOneWidget);
    expect(find.text('Radius area ruangan: 1 km.'), findsOneWidget);
    expect(find.text('Ambil lokasi'), findsOneWidget);

    final next = tester.widget<FilledButton>(
      find.widgetWithText(FilledButton, 'Lanjut'),
    );
    expect(next.onPressed, isNull);
  });
}
