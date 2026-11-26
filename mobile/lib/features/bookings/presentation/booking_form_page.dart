import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_error.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/widgets/app_chrome.dart';
import '../../../core/widgets/app_components.dart';
import '../../../core/widgets/user_quick_access_sheet.dart';
import '../../face/data/face_providers.dart';
import '../../face/data/face_repository.dart';
import '../../resources/presentation/resource_providers.dart';
import '../data/availability_schedule.dart';
import '../data/booking_repository.dart';
import 'availability_widgets.dart';
import 'booking_providers.dart';
import 'booking_selection.dart';

class BookingFormPage extends ConsumerStatefulWidget {
  const BookingFormPage({
    required this.resourceId,
    this.initialSelection,
    super.key,
  });

  final String resourceId;
  final BookingSlotSelection? initialSelection;

  @override
  ConsumerState<BookingFormPage> createState() => _BookingFormPageState();
}

class _BookingFormPageState extends ConsumerState<BookingFormPage> {
  late DateTime date;
  TimeOfDay? start;
  TimeOfDay? end;
  final title = TextEditingController();
  final notes = TextEditingController();
  String? error;
  bool loading = false;
  bool faceLoading = true;
  Map<String, dynamic>? faceStatus;
  bool faceEnrolled = false;

  /// False while the face status is unknown, so the server decides.
  bool _needsEnrollment(Map<String, dynamic>? resource) {
    final status = faceStatus;
    if (status == null || faceEnrolled) return false;
    return faceVerificationRequired(status, resource?['type']);
  }

  @override
  void initState() {
    super.initState();
    final initial = widget.initialSelection;
    date = _day(initial?.date ?? DateTime.now());
    start = initial?.start;
    end = initial?.end;
    _loadFaceStatus();
  }

  @override
  void dispose() {
    title.dispose();
    notes.dispose();
    super.dispose();
  }

  Future<void> _loadFaceStatus() async {
    try {
      final status = await ref.read(faceRepositoryProvider).status();
      if (!mounted) return;
      setState(() {
        faceStatus = status;
        faceEnrolled = status['enrolled'] == true;
        faceLoading = false;
      });
    } catch (_) {
      if (mounted) setState(() => faceLoading = false);
    }
  }

  Future<void> _openEnrollment() async {
    final result = await context.push<bool>('/face/enrollment');
    ref.invalidate(faceStatusProvider);
    if (!mounted) return;
    if (result == true) {
      setState(() => faceEnrolled = true);
    } else {
      await _loadFaceStatus();
    }
  }

  @override
  Widget build(BuildContext context) {
    final availability = ref.watch(
      availabilityProvider(AvailabilityQuery(widget.resourceId, date)),
    );
    final resource = ref.watch(resourceDetailProvider(widget.resourceId));
    final needsEnrollment = _needsEnrollment(resource.asData?.value);

    return Scaffold(
      body: SafeArea(
        bottom: false,
        child: Column(
          children: [
            AppDetailHeader(
              eyebrow: 'SIBORU',
              title: 'Buat booking',
              onBack: () {
                if (context.canPop()) {
                  context.pop();
                } else {
                  context.go('/rooms');
                }
              },
              onAvatarTap: () => showUserQuickAccessSheet(context),
            ),
            Expanded(
              child: ListView(
                padding: const EdgeInsets.fromLTRB(18, 8, 18, 24),
                children: [
                  resource.maybeWhen(
                    data: (room) => _RoomHeader(room: room),
                    orElse: () => const _RoomHeader(room: null),
                  ),
                  if (needsEnrollment) ...[
                    const SizedBox(height: 14),
                    _FaceEnrollmentBanner(onEnroll: _openEnrollment),
                  ],
                  const SizedBox(height: 18),
                  const _SectionTitle('Tanggal & jadwal'),
                  const SizedBox(height: 8),
                  _ScheduleCard(
                    date: date,
                    availability: availability,
                    start: start,
                    end: end,
                    onPickDate: _pickDate,
                    onOpenSchedule: _openSchedule,
                    onSelectSlot: _selectSlot,
                    onRetry: () => ref.invalidate(
                      availabilityProvider(
                        AvailabilityQuery(widget.resourceId, date),
                      ),
                    ),
                  ),
                  const SizedBox(height: 18),
                  const _SectionTitle('Waktu booking'),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      Expanded(
                        child: _TimeCard(
                          label: 'Mulai',
                          icon: Icons.schedule_rounded,
                          value: _timeLabel(start),
                          onTap: () => _pickTime(isStart: true),
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: _TimeCard(
                          label: 'Selesai',
                          icon: Icons.timer_outlined,
                          value: _timeLabel(end),
                          onTap: () => _pickTime(isStart: false),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 18),
                  const _SectionTitle('Detail booking'),
                  const SizedBox(height: 8),
                  TextField(
                    controller: title,
                    textInputAction: TextInputAction.next,
                    decoration: const InputDecoration(
                      labelText: 'Judul booking',
                      hintText: 'Contoh: Rapat tim',
                      prefixIcon: Icon(Icons.edit_note_rounded),
                    ),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: notes,
                    maxLines: 3,
                    decoration: const InputDecoration(
                      labelText: 'Catatan (opsional)',
                      hintText: 'Tambahkan kebutuhan atau agenda',
                      alignLabelWithHint: true,
                    ),
                  ),
                  if (error != null) ...[
                    const SizedBox(height: 12),
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: AppColors.dangerSoft,
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: Row(
                        children: [
                          const Icon(
                            Icons.error_outline_rounded,
                            color: AppColors.danger,
                            size: 18,
                          ),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Text(
                              error!,
                              style: const TextStyle(
                                color: AppColors.danger,
                                fontSize: 12,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
      bottomNavigationBar: _SubmitBar(
        loading: loading || faceLoading,
        enabled: !needsEnrollment,
        start: start,
        end: end,
        onPressed: _submit,
      ),
    );
  }

  Future<void> _pickDate() async {
    final today = _day(DateTime.now());
    final picked = await showDatePicker(
      context: context,
      firstDate: today,
      lastDate: today.add(const Duration(days: 365)),
      initialDate: date.isBefore(today) ? today : date,
    );
    if (picked == null || !mounted) return;
    setState(() {
      date = _day(picked);
      start = null;
      end = null;
      error = null;
    });
  }

  Future<void> _openSchedule() async {
    final selection = await context.push<BookingSlotSelection>(
      '/rooms/${widget.resourceId}/schedule',
      extra: RoomScheduleArgs(initialDate: date, pickSlot: true),
    );
    if (selection == null || !mounted) return;
    setState(() {
      date = selection.date;
      start = selection.start;
      end = selection.end;
      error = null;
    });
  }

  Future<void> _pickTime({required bool isStart}) async {
    final selected = await showTimePicker(
      context: context,
      initialTime:
          (isStart ? start : end) ??
          (isStart ? const TimeOfDay(hour: 8, minute: 0) : TimeOfDay.now()),
    );
    if (selected == null || !mounted) return;
    setState(() {
      if (isStart) {
        start = selected;
      } else {
        end = selected;
      }
      error = null;
    });
  }

  void _selectSlot(AvailabilityInterval interval) {
    if (!interval.selectable) return;
    setState(() {
      start = TimeOfDay.fromDateTime(interval.start);
      end = TimeOfDay.fromDateTime(interval.end);
      error = null;
    });
  }

  Future<void> _submit() async {
    if (start == null || end == null) {
      setState(() => error = 'Waktu mulai dan selesai wajib dipilih.');
      return;
    }
    final starts = DateTime(
      date.year,
      date.month,
      date.day,
      start!.hour,
      start!.minute,
    );
    final ends = DateTime(
      date.year,
      date.month,
      date.day,
      end!.hour,
      end!.minute,
    );
    if (!ends.isAfter(starts)) {
      setState(() => error = 'Waktu selesai harus setelah mulai.');
      return;
    }
    final resource = ref.read(resourceDetailProvider(widget.resourceId));
    if (_needsEnrollment(resource.asData?.value)) {
      setState(
        () => error =
            'Daftarkan wajah terlebih dahulu sebelum membuat booking ruangan.',
      );
      return;
    }
    setState(() {
      loading = true;
      error = null;
    });
    try {
      await ref
          .read(bookingRepositoryProvider)
          .create(
            resourceId: widget.resourceId,
            startsAt: starts,
            endsAt: ends,
            title: title.text,
            notes: notes.text,
          );
      ref.invalidate(bookingsProvider);
      if (mounted) context.go('/bookings');
    } catch (e) {
      if (!mounted) return;
      if (e is ApiError && e.code == 'face_enrollment_required') {
        // The server asks for enrollment, so show the banner from now on.
        setState(() {
          faceStatus = const {'enabled': true};
          faceEnrolled = false;
        });
      }
      setState(() => error = apiErrorMessage(e));
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }
}

class _RoomHeader extends StatelessWidget {
  const _RoomHeader({required this.room});

  final Map<String, dynamic>? room;

  @override
  Widget build(BuildContext context) {
    final location = room?['location'];
    final locationName = location is Map
        ? location['name']?.toString()
        : location?.toString();
    final detail = room?['room'];
    final capacity = detail is Map ? detail['capacity'] : room?['capacity'];
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
                  label: 'BOOKING RUANGAN',
                  backgroundColor: Color(0x26FFFFFF),
                  foregroundColor: Colors.white,
                ),
                const SizedBox(height: 10),
                Text(
                  room?['name']?.toString() ?? 'Memuat ruangan...',
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
              ],
            ),
          ),
          const SizedBox(width: 12),
          Container(
            width: 58,
            height: 58,
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.14),
              borderRadius: BorderRadius.circular(18),
            ),
            child: const Icon(
              Icons.meeting_room_rounded,
              color: Colors.white,
              size: 30,
            ),
          ),
        ],
      ),
    );
  }
}

class _ScheduleCard extends StatelessWidget {
  const _ScheduleCard({
    required this.date,
    required this.availability,
    required this.start,
    required this.end,
    required this.onPickDate,
    required this.onOpenSchedule,
    required this.onSelectSlot,
    required this.onRetry,
  });

  final DateTime date;
  final AsyncValue<AvailabilitySchedule> availability;
  final TimeOfDay? start;
  final TimeOfDay? end;
  final VoidCallback onPickDate;
  final VoidCallback onOpenSchedule;
  final ValueChanged<AvailabilityInterval> onSelectSlot;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return AppSectionCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const AppIconBox(icon: Icons.calendar_month_rounded, size: 42),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
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
              OutlinedButton(
                onPressed: onPickDate,
                style: OutlinedButton.styleFrom(
                  minimumSize: const Size(0, 36),
                  padding: const EdgeInsets.symmetric(horizontal: 12),
                ),
                child: const Text('Ganti'),
              ),
            ],
          ),
          const Divider(height: 24),
          Row(
            children: [
              Expanded(
                child: Text(
                  'Jadwal waktu',
                  style: Theme.of(context).textTheme.titleMedium,
                ),
              ),
              TextButton(
                onPressed: onOpenSchedule,
                child: const Text('Lihat semua'),
              ),
            ],
          ),
          const SizedBox(height: 4),
          availability.when(
            loading: () => const Padding(
              padding: EdgeInsets.symmetric(vertical: 12),
              child: LinearProgressIndicator(),
            ),
            error: (error, _) => Row(
              children: [
                Expanded(
                  child: Text(
                    apiErrorMessage(error),
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                ),
                TextButton(onPressed: onRetry, child: const Text('Ulangi')),
              ],
            ),
            data: (schedule) => !schedule.hasIntervals
                ? Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: AppColors.mintSoft,
                      borderRadius: BorderRadius.circular(14),
                    ),
                    child: const Text(
                      'Tidak ada jadwal pada tanggal ini. Coba tanggal lain.',
                      style: TextStyle(color: AppColors.muted, fontSize: 12),
                    ),
                  )
                : SizedBox(
                    height: 104,
                    child: ListView.separated(
                      scrollDirection: Axis.horizontal,
                      itemCount: schedule.intervals.length,
                      separatorBuilder: (_, _) => const SizedBox(width: 8),
                      itemBuilder: (_, index) {
                        final interval = schedule.intervals[index];
                        final selected =
                            start == TimeOfDay.fromDateTime(interval.start) &&
                            end == TimeOfDay.fromDateTime(interval.end);
                        return AvailabilitySlotCard(
                          interval: interval,
                          selected: selected,
                          compact: true,
                          onTap: () => onSelectSlot(interval),
                        );
                      },
                    ),
                  ),
          ),
          if (availability.hasValue) ...[
            const SizedBox(height: 8),
            Text(
              'Ketuk slot kosong untuk mengisi waktu booking otomatis. Slot Dipesan tidak dapat dipilih.',
              style: Theme.of(context).textTheme.bodySmall,
            ),
          ],
        ],
      ),
    );
  }
}

class _TimeCard extends StatelessWidget {
  const _TimeCard({
    required this.label,
    required this.icon,
    required this.value,
    required this.onTap,
  });

  final String label;
  final IconData icon;
  final String value;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: AppColors.surface,
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: AppColors.border),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Icon(icon, size: 16, color: AppColors.primary),
                  const SizedBox(width: 4),
                  Text(label, style: Theme.of(context).textTheme.bodySmall),
                ],
              ),
              const SizedBox(height: 6),
              Text(
                value,
                style: const TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w800,
                  letterSpacing: -0.3,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _FaceEnrollmentBanner extends StatelessWidget {
  const _FaceEnrollmentBanner({required this.onEnroll});

  final VoidCallback onEnroll;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.warningSoft,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Row(
        children: [
          const Icon(Icons.face_retouching_natural, color: AppColors.warning),
          const SizedBox(width: 10),
          const Expanded(
            child: Text(
              'Wajah belum terdaftar. Daftarkan wajah sebelum booking ruangan.',
              style: TextStyle(fontSize: 12, height: 1.35),
            ),
          ),
          TextButton(onPressed: onEnroll, child: const Text('Daftar')),
        ],
      ),
    );
  }
}

class _SectionTitle extends StatelessWidget {
  const _SectionTitle(this.title);

  final String title;

  @override
  Widget build(BuildContext context) {
    return Text(title, style: Theme.of(context).textTheme.titleMedium);
  }
}

class _SubmitBar extends StatelessWidget {
  const _SubmitBar({
    required this.loading,
    required this.enabled,
    required this.start,
    required this.end,
    required this.onPressed,
  });

  final bool loading;
  final bool enabled;
  final TimeOfDay? start;
  final TimeOfDay? end;
  final VoidCallback onPressed;

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
              if (start != null && end != null) ...[
                Text(
                  '${_timeLabel(start)} - ${_timeLabel(end)} WIB',
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
                  onPressed: loading || !enabled ? null : onPressed,
                  icon: loading
                      ? const SizedBox.square(
                          dimension: 18,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.white,
                          ),
                        )
                      : const Icon(Icons.event_available_rounded),
                  label: Text(loading ? 'Memproses...' : 'Booking ruangan'),
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

String _timeLabel(TimeOfDay? value) => value == null
    ? 'Pilih jam'
    : '${value.hour.toString().padLeft(2, '0')}:${value.minute.toString().padLeft(2, '0')}';

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
