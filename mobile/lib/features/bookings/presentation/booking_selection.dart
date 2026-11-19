import 'package:flutter/material.dart';

class BookingSlotSelection {
  const BookingSlotSelection({
    required this.date,
    required this.start,
    required this.end,
  });

  final DateTime date;
  final TimeOfDay start;
  final TimeOfDay end;
}

class RoomScheduleArgs {
  const RoomScheduleArgs({this.initialDate, this.pickSlot = false});

  final DateTime? initialDate;
  final bool pickSlot;
}
