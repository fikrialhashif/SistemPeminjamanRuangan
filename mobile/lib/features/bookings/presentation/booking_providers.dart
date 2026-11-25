import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../data/availability_schedule.dart';
import '../data/booking_repository.dart';

final availabilityProvider =
    FutureProvider.family<AvailabilitySchedule, AvailabilityQuery>(
      (ref, query) => ref
          .read(bookingRepositoryProvider)
          .availability(query.resourceId, query.date),
    );
final bookingsProvider = FutureProvider<List<Map<String, dynamic>>>(
  (ref) => ref.read(bookingRepositoryProvider).list(),
);
final bookingDetailProvider =
    FutureProvider.family<Map<String, dynamic>, String>(
      (ref, id) => ref.read(bookingRepositoryProvider).show(id),
    );

class AvailabilityQuery {
  const AvailabilityQuery(this.resourceId, this.date);
  final String resourceId;
  final DateTime date;
  @override
  bool operator ==(Object other) =>
      other is AvailabilityQuery &&
      resourceId == other.resourceId &&
      date.year == other.date.year &&
      date.month == other.date.month &&
      date.day == other.date.day;
  @override
  int get hashCode => Object.hash(resourceId, date.year, date.month, date.day);
}
