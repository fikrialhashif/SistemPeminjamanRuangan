import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/core/network/dio_provider.dart';
import 'package:mobile/features/bookings/presentation/booking_detail_page.dart';

import '../../support/fake_backend.dart';

void main() {
  testWidgets('menampilkan bukti check-in pada detail booking', (tester) async {
    final backend = FakeBackend();
    final storage = FakeTokenStorage();

    await tester.pumpWidget(
      ProviderScope(
        overrides: [tokenStorageProvider.overrideWithValue(storage)],
        child: MaterialApp(
          home: Consumer(
            builder: (context, ref, _) {
              ref.read(dioProvider).httpClientAdapter = backend;
              return const BookingDetailPage(
                bookingId: '9',
                justCheckedIn: true,
              );
            },
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Detail booking'), findsOneWidget);
    expect(find.text('Check-in berhasil'), findsOneWidget);
    expect(find.text('Sudah check-in'), findsOneWidget);
    expect(find.text('Bukti check-in'), findsOneWidget);
    expect(find.text('Gedung Utama'), findsOneWidget);
    expect(find.text('QR ruangan'), findsOneWidget);
    expect(find.text('Lokasi'), findsNWidgets(2));
    expect(find.text('Wajah'), findsOneWidget);
  });
}
