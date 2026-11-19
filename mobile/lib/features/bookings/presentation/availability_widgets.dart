import 'package:flutter/material.dart';

import '../../../core/formatting/formatters.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/widgets/app_components.dart';

DateTime? availabilityStart(Map<String, dynamic> slot) =>
    parseWibDateTime(slot['start']);

DateTime? availabilityEnd(Map<String, dynamic> slot) =>
    parseWibDateTime(slot['end']);

String availabilityDurationLabel(Map<String, dynamic> slot) {
  final start = availabilityStart(slot);
  final end = availabilityEnd(slot);
  if (start == null || end == null) return '-';
  final minutes = end.difference(start).inMinutes;
  if (minutes < 60) return '$minutes menit';
  final hours = minutes ~/ 60;
  final remainder = minutes % 60;
  return remainder == 0 ? '$hours jam' : '$hours jam $remainder mnt';
}

class AvailabilitySlotCard extends StatelessWidget {
  const AvailabilitySlotCard({
    required this.slot,
    required this.selected,
    required this.onTap,
    this.compact = false,
    super.key,
  });

  final Map<String, dynamic> slot;
  final bool selected;
  final VoidCallback onTap;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final start = formatWibTime(slot['start']);
    final end = formatWibTime(slot['end']);
    final duration = availabilityDurationLabel(slot);

    return Material(
      color: selected ? AppColors.mintSoft : AppColors.surface,
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Container(
          width: compact ? 168 : null,
          padding: EdgeInsets.all(compact ? 12 : 14),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(16),
            border: Border.all(
              color: selected ? AppColors.primary : AppColors.border,
              width: selected ? 1.5 : 1,
            ),
          ),
          child: compact
              ? Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        const Icon(
                          Icons.schedule_rounded,
                          size: 14,
                          color: AppColors.primary,
                        ),
                        const SizedBox(width: 4),
                        Expanded(
                          child: Text(
                            '$start - $end',
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
                    const AppPill(label: 'Kosong'),
                  ],
                )
              : Row(
                  children: [
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          start,
                          style: const TextStyle(
                            fontSize: 20,
                            fontWeight: FontWeight.w800,
                            letterSpacing: -0.4,
                          ),
                        ),
                        Text(
                          's.d. $end',
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
                          const Text(
                            'Slot kosong',
                            style: TextStyle(
                              color: AppColors.muted,
                              fontSize: 12,
                            ),
                          ),
                        ],
                      ),
                    ),
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
