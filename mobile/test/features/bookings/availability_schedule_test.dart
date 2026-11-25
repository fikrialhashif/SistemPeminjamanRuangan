import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/features/bookings/data/availability_schedule.dart';

void main() {
  test('memetakan interval server menjadi slot kosong dan dipesan', () {
    final schedule = AvailabilitySchedule.fromJson({
      'date': '2026-11-17',
      'open_time': '07:00',
      'close_time': '22:00',
      'hours_source': 'room',
      'intervals': [
        {
          'start': '2026-11-16T23:00:00Z',
          'end': '2026-11-17T00:00:00Z',
          'available': true,
          'status_detail': null,
        },
        {
          'start': '2026-11-17T00:00:00Z',
          'end': '2026-11-17T01:00:00Z',
          'available': false,
          'status_detail': 'approved',
        },
      ],
    }, requestedDate: DateTime(2026, 11, 17));

    expect(schedule.openTime, '07:00');
    expect(schedule.closeTime, '22:00');
    expect(schedule.hoursSource, 'room');
    expect(schedule.intervals.length, 2);
    expect(schedule.freeIntervals.length, 1);
    expect(schedule.intervals.first.selectable, isTrue);
    expect(schedule.intervals.first.label, 'Tersedia');
    expect(schedule.intervals.last.selectable, isFalse);
    expect(schedule.intervals.last.label, 'Dipesan');
    expect(schedule.intervals.last.detailLabel, 'Disetujui');
  });

  test('mendukung payload slots lama dan jam operasional fallback', () {
    final schedule = AvailabilitySchedule.fromJson({
      'date': '2026-11-17',
      'slots': [
        {
          'starts_at': '2026-11-16T19:00:00Z',
          'ends_at': '2026-11-16T20:00:00Z',
          'status': 'free',
        },
        {
          'starts_at': '2026-11-16T20:00:00Z',
          'ends_at': '2026-11-16T21:00:00Z',
          'status': 'booked',
          'status_detail': 'pending',
        },
      ],
    }, requestedDate: DateTime(2026, 11, 17));

    expect(schedule.openTime, '02:00');
    expect(schedule.closeTime, '04:00');
    expect(schedule.hoursSource, 'server');
    expect(schedule.freeIntervals.length, 1);
    expect(schedule.intervals.last.detailLabel, 'Menunggu persetujuan');
  });
}
