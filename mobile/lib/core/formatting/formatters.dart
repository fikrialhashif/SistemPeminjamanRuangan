const _months = [
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

const _wibOffset = Duration(hours: 7);

DateTime? parseWibDateTime(Object? value) {
  if (value is! String || value.isEmpty) return null;
  final parsed = DateTime.tryParse(value);
  if (parsed == null) return null;
  return parsed.toUtc().add(_wibOffset);
}

String formatWibDateTime(Object? value) {
  final date = parseWibDateTime(value);
  if (date == null) return '-';
  final hour = date.hour.toString().padLeft(2, '0');
  final minute = date.minute.toString().padLeft(2, '0');
  return '${date.day} ${_months[date.month - 1]} ${date.year} $hour:$minute WIB';
}

String formatWibDate(Object? value) {
  final date = parseWibDateTime(value);
  if (date == null) return '-';
  return '${date.day} ${_months[date.month - 1]} ${date.year}';
}

String formatWibTime(Object? value) {
  final date = parseWibDateTime(value);
  if (date == null) return '-';
  final hour = date.hour.toString().padLeft(2, '0');
  final minute = date.minute.toString().padLeft(2, '0');
  return '$hour:$minute';
}

String statusLabel(Object? status) =>
    const {
      'pending': 'Menunggu persetujuan',
      'approved': 'Disetujui',
      'checked_in': 'Sudah check-in',
      'completed': 'Selesai',
      'rejected': 'Ditolak',
      'cancelled': 'Dibatalkan',
      'expired': 'Kedaluwarsa',
      'no_show': 'Tidak hadir',
      'waitlisted': 'Dalam antrean',
      'reserved': 'Dipesan',
      'handed_over': 'Diserahkan',
      'in_use': 'Sedang dipakai',
      'returned': 'Dikembalikan',
      'closed': 'Ditutup',
    }[status?.toString()] ??
    (status?.toString() ?? '-');
