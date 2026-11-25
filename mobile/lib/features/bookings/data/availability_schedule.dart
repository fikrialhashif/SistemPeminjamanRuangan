import '../../../core/formatting/formatters.dart';

enum AvailabilityIntervalKind { free, booked }

class AvailabilityInterval {
  const AvailabilityInterval({
    required this.start,
    required this.end,
    required this.kind,
    this.statusDetail,
  });

  final DateTime start;
  final DateTime end;
  final AvailabilityIntervalKind kind;
  final String? statusDetail;

  bool get selectable => kind == AvailabilityIntervalKind.free;

  String get label => selectable ? 'Tersedia' : 'Dipesan';

  String get detailLabel =>
      statusDetail == null ? label : statusLabel(statusDetail);

  factory AvailabilityInterval.fromJson(Map<String, dynamic> json) {
    final start = parseWibDateTime(json['start'] ?? json['starts_at']);
    final end = parseWibDateTime(json['end'] ?? json['ends_at']);
    if (start == null || end == null) {
      throw const FormatException('Rentang waktu jadwal tidak valid.');
    }
    final rawAvailable = json['available'];
    final available = rawAvailable is bool
        ? rawAvailable
        : json['status']?.toString() == 'free';
    return AvailabilityInterval(
      start: start,
      end: end,
      kind: available
          ? AvailabilityIntervalKind.free
          : AvailabilityIntervalKind.booked,
      statusDetail: json['status_detail']?.toString(),
    );
  }
}

class AvailabilitySchedule {
  const AvailabilitySchedule({
    required this.date,
    required this.openTime,
    required this.closeTime,
    required this.hoursSource,
    required this.intervals,
  });

  final DateTime date;
  final String openTime;
  final String closeTime;
  final String hoursSource;
  final List<AvailabilityInterval> intervals;

  List<AvailabilityInterval> get freeIntervals =>
      intervals.where((interval) => interval.selectable).toList();

  bool get hasIntervals => intervals.isNotEmpty;

  factory AvailabilitySchedule.fromJson(
    Map<String, dynamic> json, {
    required DateTime requestedDate,
  }) {
    final rawIntervals = json['intervals'] ?? json['slots'] ?? const [];
    final intervals = (rawIntervals as List)
        .whereType<Map>()
        .map(
          (item) =>
              AvailabilityInterval.fromJson(Map<String, dynamic>.from(item)),
        )
        .toList();
    final parsedDate = DateTime.tryParse(json['date']?.toString() ?? '');

    return AvailabilitySchedule(
      date: parsedDate ?? requestedDate,
      openTime:
          json['open_time']?.toString() ??
          (intervals.isEmpty ? '-' : _clock(intervals.first.start)),
      closeTime:
          json['close_time']?.toString() ??
          (intervals.isEmpty ? '-' : _clock(intervals.last.end)),
      hoursSource: json['hours_source']?.toString() ?? 'server',
      intervals: intervals,
    );
  }
}

String _clock(DateTime value) =>
    '${value.hour.toString().padLeft(2, '0')}:${value.minute.toString().padLeft(2, '0')}';
