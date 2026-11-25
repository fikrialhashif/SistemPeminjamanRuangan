import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/widgets/app_chrome.dart';
import '../../../core/widgets/app_components.dart';
import '../../../core/widgets/status_views.dart';
import '../../resources/presentation/resource_providers.dart';
import '../data/availability_schedule.dart';
import 'availability_widgets.dart';
import 'booking_providers.dart';
import 'booking_selection.dart';

class RoomSchedulePage extends ConsumerStatefulWidget {
  const RoomSchedulePage({
    required this.resourceId,
    this.initialDate,
    this.pickSlot = false,
    super.key,
  });

  final String resourceId;
  final DateTime? initialDate;
  final bool pickSlot;

  @override
  ConsumerState<RoomSchedulePage> createState() => _RoomSchedulePageState();
}

class _RoomSchedulePageState extends ConsumerState<RoomSchedulePage> {
  late DateTime date;
  AvailabilityInterval? selectedInterval;

  @override
  void initState() {
    super.initState();
    date = _day(widget.initialDate ?? DateTime.now());
  }

  @override
  Widget build(BuildContext context) {
    final resource = ref.watch(resourceDetailProvider(widget.resourceId));
    final availability = ref.watch(
      availabilityProvider(AvailabilityQuery(widget.resourceId, date)),
    );

    return Scaffold(
      body: SafeArea(
        bottom: false,
        child: Column(
          children: [
            AppDetailHeader(
              eyebrow: 'JADWAL KETERSEDIAAN',
              title: 'Jadwal ruangan',
              onBack: () {
                if (context.canPop()) {
                  context.pop();
                } else {
                  context.go('/rooms');
                }
              },
            ),
            Expanded(
              child: resource.when(
                loading: () => const Center(
                  child: CircularProgressIndicator(
                    semanticsLabel: 'Memuat detail ruangan',
                  ),
                ),
                error: (error, _) => ErrorView(
                  error: error,
                  onRetry: () =>
                      ref.invalidate(resourceDetailProvider(widget.resourceId)),
                ),
                data: (room) => RefreshIndicator(
                  onRefresh: () async {
                    ref.invalidate(
                      availabilityProvider(
                        AvailabilityQuery(widget.resourceId, date),
                      ),
                    );
                  },
                  child: ListView(
                    padding: const EdgeInsets.fromLTRB(18, 8, 18, 24),
                    children: [
                      _RoomSummary(
                        room: room,
                        schedule: availability.asData?.value,
                      ),
                      const SizedBox(height: 14),
                      _DateNavigator(
                        date: date,
                        onPrevious: _canGoPrevious
                            ? () => _setDate(
                                date.subtract(const Duration(days: 1)),
                              )
                            : null,
                        onNext: () =>
                            _setDate(date.add(const Duration(days: 1))),
                        onPick: _pickDate,
                        onToday: () => _setDate(DateTime.now()),
                        onTomorrow: () => _setDate(
                          DateTime.now().add(const Duration(days: 1)),
                        ),
                        onNextWeek: () => _setDate(
                          DateTime.now().add(const Duration(days: 7)),
                        ),
                      ),
                      const SizedBox(height: 18),
                      availability.when(
                        loading: () => const Padding(
                          padding: EdgeInsets.only(top: 20),
                          child: LinearProgressIndicator(),
                        ),
                        error: (error, _) => ErrorView(
                          error: error,
                          onRetry: () => ref.invalidate(
                            availabilityProvider(
                              AvailabilityQuery(widget.resourceId, date),
                            ),
                          ),
                        ),
                        data: (schedule) => _SlotsSection(
                          schedule: schedule,
                          selectedInterval: selectedInterval,
                          onSelect: (interval) =>
                              setState(() => selectedInterval = interval),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
      bottomNavigationBar: _ScheduleCta(
        selection: _selection,
        pickSlot: widget.pickSlot,
        onPressed: _selection == null ? null : _confirm,
      ),
    );
  }

  bool get _canGoPrevious => date.isAfter(_day(DateTime.now()));

  BookingSlotSelection? get _selection {
    final interval = selectedInterval;
    if (interval == null || !interval.selectable) return null;
    return BookingSlotSelection(
      date: date,
      start: TimeOfDay.fromDateTime(interval.start),
      end: TimeOfDay.fromDateTime(interval.end),
    );
  }

  void _setDate(DateTime value) {
    setState(() {
      date = _day(value);
      selectedInterval = null;
    });
  }

  Future<void> _pickDate() async {
    final today = _day(DateTime.now());
    final picked = await showDatePicker(
      context: context,
      firstDate: today,
      lastDate: today.add(const Duration(days: 365)),
      initialDate: date.isBefore(today) ? today : date,
    );
    if (picked != null && mounted) _setDate(picked);
  }

  void _confirm() {
    final selection = _selection;
    if (selection == null) return;
    if (widget.pickSlot) {
      context.pop(selection);
      return;
    }
    context.push('/bookings/new/${widget.resourceId}', extra: selection);
  }
}

class _RoomSummary extends StatelessWidget {
  const _RoomSummary({required this.room, required this.schedule});

  final Map<String, dynamic> room;
  final AvailabilitySchedule? schedule;

  @override
  Widget build(BuildContext context) {
    final location = room['location'];
    final locationName = location is Map
        ? location['name']?.toString()
        : location?.toString();
    final detail = room['room'];
    final capacity = detail is Map ? detail['capacity'] : room['capacity'];
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [AppColors.primaryDark, AppColors.primary],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(22),
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const AppPill(
                  label: 'RUANGAN',
                  backgroundColor: Color(0x26FFFFFF),
                  foregroundColor: Colors.white,
                ),
                const SizedBox(height: 10),
                Text(
                  room['name']?.toString() ?? 'Ruangan',
                  style: Theme.of(context).textTheme.titleLarge
                      ?.copyWith(color: Colors.white, fontSize: 20),
                ),
                const SizedBox(height: 5),
                Text(
                  [
                    locationName ?? 'Lokasi belum tersedia',
                    if (capacity != null) 'Kapasitas $capacity',
                  ].join(' • '),
                  style: TextStyle(
                    color: Colors.white.withValues(alpha: 0.78),
                    fontSize: 12,
                  ),
                ),
                const SizedBox(height: 10),
                Row(
                  children: [
                    Icon(
                      Icons.schedule_rounded,
                      size: 14,
                      color: Colors.white.withValues(alpha: 0.78),
                    ),
                    const SizedBox(width: 4),
                    Text(
                      'Jam operasional ${_hourLabel(schedule?.openTime)} - ${_hourLabel(schedule?.closeTime)} WIB',
                      style: TextStyle(
                        color: Colors.white.withValues(alpha: 0.78),
                        fontSize: 11,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(width: 12),
          Container(
            width: 60,
            height: 60,
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.14),
              borderRadius: BorderRadius.circular(18),
            ),
            child: const Icon(
              Icons.meeting_room_rounded,
              color: Colors.white,
              size: 32,
            ),
          ),
        ],
      ),
    );
  }
}

class _DateNavigator extends StatelessWidget {
  const _DateNavigator({
    required this.date,
    required this.onPrevious,
    required this.onNext,
    required this.onPick,
    required this.onToday,
    required this.onTomorrow,
    required this.onNextWeek,
  });

  final DateTime date;
  final VoidCallback? onPrevious;
  final VoidCallback onNext;
  final VoidCallback onPick;
  final VoidCallback onToday;
  final VoidCallback onTomorrow;
  final VoidCallback onNextWeek;

  @override
  Widget build(BuildContext context) {
    return AppSectionCard(
      child: Column(
        children: [
          Row(
            children: [
              IconButton(
                tooltip: 'Hari sebelumnya',
                onPressed: onPrevious,
                icon: const Icon(Icons.chevron_left_rounded),
              ),
              Expanded(
                child: InkWell(
                  borderRadius: BorderRadius.circular(14),
                  onTap: onPick,
                  child: Padding(
                    padding: const EdgeInsets.symmetric(vertical: 6),
                    child: Column(
                      children: [
                        Text(
                          _dayName(date),
                          style: Theme.of(context).textTheme.titleMedium,
                        ),
                        const SizedBox(height: 2),
                        Text(
                          _dateLabel(date),
                          style: Theme.of(context).textTheme.bodySmall,
                        ),
                      ],
                    ),
                  ),
                ),
              ),
              IconButton(
                tooltip: 'Hari berikutnya',
                onPressed: onNext,
                icon: const Icon(Icons.chevron_right_rounded),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Row(
            children: [
              Expanded(
                child: _QuickDateButton(label: 'Hari ini', onTap: onToday),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: _QuickDateButton(label: 'Besok', onTap: onTomorrow),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: _QuickDateButton(label: '+7 hari', onTap: onNextWeek),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _QuickDateButton extends StatelessWidget {
  const _QuickDateButton({required this.label, required this.onTap});

  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return TextButton(
      onPressed: onTap,
      style: TextButton.styleFrom(
        backgroundColor: AppColors.mintSoft,
        foregroundColor: AppColors.primary,
        minimumSize: const Size(0, 38),
        padding: const EdgeInsets.symmetric(horizontal: 8),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
        textStyle: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700),
      ),
      child: Text(label),
    );
  }
}

class _SlotsSection extends StatelessWidget {
  const _SlotsSection({
    required this.schedule,
    required this.selectedInterval,
    required this.onSelect,
  });

  final AvailabilitySchedule schedule;
  final AvailabilityInterval? selectedInterval;
  final ValueChanged<AvailabilityInterval> onSelect;

  @override
  Widget build(BuildContext context) {
    final freeCount = schedule.freeIntervals.length;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Expanded(
              child: Text(
                'Jadwal waktu',
                style: Theme.of(context).textTheme.titleLarge,
              ),
            ),
            AppPill(
              label: '$freeCount slot kosong',
              icon: Icons.schedule_rounded,
              backgroundColor: AppColors.mintStrong,
            ),
          ],
        ),
        const SizedBox(height: 4),
        Text(
          'Rentang berlabel Dipesan tidak dapat dipilih. Status mengikuti data booking dari server.',
          style: Theme.of(context).textTheme.bodySmall,
        ),
        const SizedBox(height: 12),
        if (!schedule.hasIntervals)
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(24),
            decoration: BoxDecoration(
              color: AppColors.surface,
              borderRadius: BorderRadius.circular(18),
              border: Border.all(color: AppColors.border),
            ),
            child: Column(
              children: [
                const Icon(
                  Icons.event_busy_rounded,
                  color: AppColors.muted,
                  size: 34,
                ),
                const SizedBox(height: 8),
                Text(
                  'Tidak ada jadwal pada tanggal ini.',
                  textAlign: TextAlign.center,
                  style: Theme.of(context).textTheme.bodyMedium,
                ),
                const SizedBox(height: 4),
                Text(
                  'Coba pilih tanggal lain.',
                  style: Theme.of(context).textTheme.bodySmall,
                ),
              ],
            ),
          )
        else
          for (final interval in schedule.intervals) ...[
            AvailabilitySlotCard(
              interval: interval,
              selected:
                  selectedInterval != null &&
                  selectedInterval!.start == interval.start &&
                  selectedInterval!.end == interval.end,
              onTap: () => onSelect(interval),
            ),
            const SizedBox(height: 10),
          ],
      ],
    );
  }
}

class _ScheduleCta extends StatelessWidget {
  const _ScheduleCta({
    required this.selection,
    required this.pickSlot,
    required this.onPressed,
  });

  final BookingSlotSelection? selection;
  final bool pickSlot;
  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: const BoxDecoration(
        color: AppColors.surface,
        border: Border(top: BorderSide(color: AppColors.border)),
      ),
      child: SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(18, 12, 18, 12),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (selection != null) ...[
                Text(
                  '${_timeLabel(selection!.start)} - ${_timeLabel(selection!.end)} WIB',
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: AppColors.text,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 8),
              ],
              SizedBox(
                width: double.infinity,
                child: FilledButton.icon(
                  onPressed: onPressed,
                  icon: Icon(
                    pickSlot
                        ? Icons.check_circle_outline_rounded
                        : Icons.calendar_month_rounded,
                  ),
                  label: Text(
                    selection == null
                        ? 'Pilih slot kosong'
                        : pickSlot
                        ? 'Gunakan slot ini'
                        : 'Booking slot ini',
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

DateTime _day(DateTime value) => DateTime(value.year, value.month, value.day);

String _timeLabel(TimeOfDay value) =>
    '${value.hour.toString().padLeft(2, '0')}:${value.minute.toString().padLeft(2, '0')}';

String _hourLabel(String? value) {
  if (value == null || value.isEmpty) return '-';
  return value.replaceFirst(':', '.');
}

const _dayNames = [
  'Senin',
  'Selasa',
  'Rabu',
  'Kamis',
  'Jumat',
  'Sabtu',
  'Minggu',
];

const _monthNames = [
  'Jan',
  'Feb',
  'Mar',
  'Apr',
  'Mei',
  'Jun',
  'Jul',
  'Agu',
  'Sep',
  'Okt',
  'Nov',
  'Des',
];

String _dayName(DateTime date) => _dayNames[date.weekday - 1];

String _dateLabel(DateTime date) =>
    '${date.day} ${_monthNames[date.month - 1]} ${date.year}';
