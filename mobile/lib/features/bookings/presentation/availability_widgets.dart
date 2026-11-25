import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/widgets/app_components.dart';
import '../data/availability_schedule.dart';

DateTime availabilityStart(AvailabilityInterval interval) => interval.start;

DateTime availabilityEnd(AvailabilityInterval interval) => interval.end;

String availabilityDurationLabel(AvailabilityInterval interval) {
  final minutes = interval.end.difference(interval.start).inMinutes;
  if (minutes < 60) return '$minutes menit';
  final hours = minutes ~/ 60;
  final remainder = minutes % 60;
  return remainder == 0 ? '$hours jam' : '$hours jam $remainder mnt';
}

String availabilityTimeLabel(AvailabilityInterval interval) =>
    '${formatClock(interval.start)} - ${formatClock(interval.end)}';

String formatClock(DateTime value) =>
    '${value.hour.toString().padLeft(2, '0')}:${value.minute.toString().padLeft(2, '0')}';

class AvailabilitySlotCard extends StatelessWidget {
  const AvailabilitySlotCard({
    required this.interval,
    required this.selected,
    required this.onTap,
    this.compact = false,
    super.key,
  });

  final AvailabilityInterval interval;
  final bool selected;
  final VoidCallback onTap;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final booked = !interval.selectable;
    final timeLabel = availabilityTimeLabel(interval);
    final duration = availabilityDurationLabel(interval);
    final background = booked
        ? Theme.of(context).colorScheme.surfaceContainerHighest
        : selected
        ? AppColors.mintSoft
        : AppColors.surface;
    final borderColor = booked
        ? AppColors.border
        : selected
        ? AppColors.primary
        : AppColors.border;

    return Material(
      color: background,
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        onTap: booked ? null : onTap,
        borderRadius: BorderRadius.circular(16),
        child: Container(
          width: compact ? 168 : null,
          padding: EdgeInsets.all(compact ? 12 : 14),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: borderColor, width: selected ? 1.5 : 1),
          ),
          child: compact
              ? Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Icon(
                          booked
                              ? Icons.event_busy_rounded
                              : Icons.schedule_rounded,
                          size: 14,
                          color: booked ? AppColors.muted : AppColors.primary,
                        ),
                        const SizedBox(width: 4),
                        Expanded(
                          child: Text(
                            timeLabel,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              fontWeight: FontWeight.w700,
                              fontSize: 13,
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 6),
                    Text(
                      duration,
                      style: Theme.of(context).textTheme.bodySmall,
                    ),
                    const SizedBox(height: 6),
                    AppPill(
                      label: booked ? interval.detailLabel : 'Kosong',
                      backgroundColor: booked
                          ? AppColors.border
                          : AppColors.mintSoft,
                      foregroundColor: booked
                          ? AppColors.muted
                          : AppColors.primary,
                    ),
                  ],
                )
              : Row(
                  children: [
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          formatClock(interval.start),
                          style: const TextStyle(
                            fontSize: 20,
                            fontWeight: FontWeight.w800,
                            letterSpacing: -0.4,
                          ),
                        ),
                        Text(
                          's.d. ${formatClock(interval.end)}',
                          style: Theme.of(context).textTheme.bodySmall,
                        ),
                      ],
                    ),
                    const SizedBox(width: 14),
                    Container(width: 1, height: 42, color: AppColors.border),
                    const SizedBox(width: 14),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            duration,
                            style: const TextStyle(
                              fontWeight: FontWeight.w700,
                              fontSize: 13,
                            ),
                          ),
                          const SizedBox(height: 3),
                          Text(
                            booked ? interval.detailLabel : 'Slot kosong',
                            style: const TextStyle(
                              color: AppColors.muted,
                              fontSize: 12,
                            ),
                          ),
                        ],
                      ),
                    ),
                    if (!booked)
                      Icon(
                        selected
                            ? Icons.check_circle_rounded
                            : Icons.radio_button_unchecked_rounded,
                        color: selected ? AppColors.primary : AppColors.muted,
                      ),
                  ],
                ),
        ),
      ),
    );
  }
}
