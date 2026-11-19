<?php

namespace Database\Seeders;

use App\Enums\BookingStatus;
use App\Enums\CheckInResult;
use App\Enums\LoanStatus;
use App\Enums\Role as RoleEnum;
use App\Models\AssetLoan;
use App\Models\Booking;
use App\Models\ConditionReport;
use App\Models\DeviceToken;
use App\Models\FaceEnrollment;
use App\Models\FaceVerification;
use App\Models\LoanEvent;
use App\Models\NotificationLog;
use App\Models\Resource;
use App\Models\Role;
use App\Models\Strike;
use App\Models\Suspension;
use App\Models\User;
use App\Models\WaitlistEntry;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class WorkflowDummySeeder extends Seeder
{
    public function run(): void
    {
        if (Booking::where('title', 'like', '[DUMMY]%')->exists()) {
            $this->command?->warn('Data dummy alur kerja sudah ada. Seeder dilewati agar tidak duplikat.');

            return;
        }

        Model::unguard();

        try {
            DB::transaction(function () {
                $now = Carbon::now();

                $plainPassword = Hash::make('password');

                $roles = Role::pluck('id', 'name');

                $memberRole = $roles[RoleEnum::MEMBER->value];
                $approverRole = $roles[RoleEnum::APPROVER->value];

                $makeUser = function (string $name, string $email, int $departmentId, int $roleId) use ($plainPassword) {
                    $user = User::firstOrCreate(
                        ['email' => $email],
                        ['name' => $name, 'password' => $plainPassword, 'department_id' => $departmentId],
                    );
                    $user->roles()->syncWithoutDetaching([$roleId]);

                    return $user;
                };

                $deptTi = DB::table('departments')->where('code', 'TI')->value('id');
                $deptSi = DB::table('departments')->where('code', 'SI')->value('id');
                $deptMn = DB::table('departments')->where('code', 'MN')->value('id');

                $admin = User::where('email', 'admin@example.com')->firstOrFail();
                $manager = User::where('email', 'manager@example.com')->firstOrFail();
                $dewi = $makeUser('Dewi Lestari', 'dewi.lestari@example.com', $deptTi, $approverRole);
                $rina = $makeUser('Rina Marlina', 'rina.marlina@example.com', $deptSi, $approverRole);

                $budi = $makeUser('Budi Santoso', 'budi.santoso@example.com', $deptTi, $memberRole);
                $sari = $makeUser('Sari Wulandari', 'sari.wulandari@example.com', $deptSi, $memberRole);
                $agus = $makeUser('Agus Pratama', 'agus.pratama@example.com', $deptMn, $memberRole);
                $joko = $makeUser('Joko Wibowo', 'joko.wibowo@example.com', $deptTi, $memberRole);

                $room1 = Resource::where('name', 'Ruang Rapat A1')->firstOrFail();
                $room2 = Resource::where('name', 'Lab Komputer B2')->firstOrFail();
                $asset1Resource = Resource::where('name', 'Proyektor Portable')->firstOrFail();
                $asset2Resource = Resource::where('name', 'Kamera DSLR')->firstOrFail();
                $asset1 = $asset1Resource->asset()->firstOrFail();
                $asset2 = $asset2Resource->asset()->firstOrFail();

                $room1Loc = $room1->location;
                $room2Loc = $room2->location;

                $at = fn (int $days, int $hour, int $minute = 0) => $now->copy()->addDays($days)->setTime($hour, $minute);

                $timestamps = fn (Carbon $c) => ['created_at' => $c, 'updated_at' => $c];

                $bookings = [];

                $bookings['completed_room'] = Booking::create([
                    'user_id' => $budi->id,
                    'resource_id' => $room1->id,
                    'title' => '[DUMMY] Rapat Koordinasi Tim TI',
                    'starts_at' => $at(-5, 9),
                    'ends_at' => $at(-5, 11),
                    'status' => BookingStatus::COMPLETED,
                    'notes' => 'Agenda: evaluasi sprint dan rencana minggu depan.',
                ] + $timestamps($at(-6, 14)));

                $bookings['no_show_room'] = Booking::create([
                    'user_id' => $joko->id,
                    'resource_id' => $room1->id,
                    'title' => '[DUMMY] Review Sprint',
                    'starts_at' => $at(-3, 13),
                    'ends_at' => $at(-3, 15),
                    'status' => BookingStatus::NO_SHOW,
                    'notes' => 'Pemesan tidak melakukan check-in sampai batas waktu.',
                ] + $timestamps($at(-4, 10)));

                $bookings['completed_lab'] = Booking::create([
                    'user_id' => $sari->id,
                    'resource_id' => $room2->id,
                    'title' => '[DUMMY] Praktikum Basis Data',
                    'starts_at' => $at(-2, 10),
                    'ends_at' => $at(-2, 12),
                    'status' => BookingStatus::COMPLETED,
                ] + $timestamps($at(-3, 9)));

                $bookings['checked_in_room'] = Booking::create([
                    'user_id' => $agus->id,
                    'resource_id' => $room1->id,
                    'title' => '[DUMMY] Diskusi Proyek Akhir',
                    'starts_at' => $now->copy()->subMinutes(30),
                    'ends_at' => $now->copy()->addMinutes(90),
                    'status' => BookingStatus::CHECKED_IN,
                ] + $timestamps($now->copy()->subDay()));

                $bookings['approved_room'] = Booking::create([
                    'user_id' => $budi->id,
                    'resource_id' => $room1->id,
                    'title' => '[DUMMY] Rapat Mingguan Divisi',
                    'starts_at' => $at(1, 9),
                    'ends_at' => $at(1, 11),
                    'status' => BookingStatus::APPROVED,
                ] + $timestamps($at(-1, 16)));

                $bookings['pending_room'] = Booking::create([
                    'user_id' => $sari->id,
                    'resource_id' => $room1->id,
                    'title' => '[DUMMY] Sosialisasi Kebijakan Baru',
                    'starts_at' => $at(2, 13),
                    'ends_at' => $at(2, 15),
                    'status' => BookingStatus::PENDING,
                ] + $timestamps($now->copy()->subHours(2)));

                $bookings['rejected_room'] = Booking::create([
                    'user_id' => $joko->id,
                    'resource_id' => $room1->id,
                    'title' => '[DUMMY] Rapat Internal Mendadak',
                    'starts_at' => $at(3, 9),
                    'ends_at' => $at(3, 11),
                    'status' => BookingStatus::REJECTED,
                    'notes' => 'Ditolak karena bentrok dengan agenda institusi.',
                ] + $timestamps($at(-2, 11)));

                $bookings['cancelled_lab'] = Booking::create([
                    'user_id' => $sari->id,
                    'resource_id' => $room2->id,
                    'title' => '[DUMMY] Workshop Git',
                    'starts_at' => $at(4, 10),
                    'ends_at' => $at(4, 12),
                    'status' => BookingStatus::CANCELLED,
                    'cancelled_at' => $now->copy()->subHour(),
                ] + $timestamps($at(-2, 10)));

                $bookings['waitlisted_room'] = Booking::create([
                    'user_id' => $agus->id,
                    'resource_id' => $room1->id,
                    'title' => '[DUMMY] Antrian Ruang Rapat',
                    'starts_at' => $at(1, 9),
                    'ends_at' => $at(1, 11),
                    'status' => BookingStatus::WAITLISTED,
                ] + $timestamps($now->copy()->subHour()));

                $bookings['completed_camera'] = Booking::create([
                    'user_id' => $budi->id,
                    'resource_id' => $asset2Resource->id,
                    'title' => '[DUMMY] Dokumentasi Acara Kampus',
                    'starts_at' => $at(-7, 8),
                    'ends_at' => $at(-7, 17),
                    'status' => BookingStatus::COMPLETED,
                ] + $timestamps($at(-8, 9)));

                $bookings['in_use_projector'] = Booking::create([
                    'user_id' => $agus->id,
                    'resource_id' => $asset1Resource->id,
                    'title' => '[DUMMY] Peminjaman Proyektor Rapat',
                    'starts_at' => $now->copy()->subHours(2),
                    'ends_at' => $now->copy()->addHours(2),
                    'status' => BookingStatus::CHECKED_IN,
                ] + $timestamps($now->copy()->subDay()));

                $bookings['reserved_camera'] = Booking::create([
                    'user_id' => $sari->id,
                    'resource_id' => $asset2Resource->id,
                    'title' => '[DUMMY] Peminjaman Kamera Media',
                    'starts_at' => $at(2, 8),
                    'ends_at' => $at(2, 12),
                    'status' => BookingStatus::APPROVED,
                ] + $timestamps($at(-1, 13)));

                $bookings['pending_camera'] = Booking::create([
                    'user_id' => $budi->id,
                    'resource_id' => $asset2Resource->id,
                    'title' => '[DUMMY] Peminjaman Kamera Liputan',
                    'starts_at' => $at(3, 13),
                    'ends_at' => $at(3, 17),
                    'status' => BookingStatus::PENDING,
                ] + $timestamps($now->copy()->subHours(3)));

                $approval = function (Booking $booking, int $level, ?User $approver, string $status, ?Carbon $decidedAt, ?string $notes = null, ?Carbon $createdAt = null) {
                    $createdAt ??= $booking->created_at;
                    $booking->approvals()->create([
                        'level' => $level,
                        'approver_id' => $approver?->id,
                        'status' => $status,
                        'notes' => $notes,
                        'decided_at' => $decidedAt,
                        'expires_at' => $decidedAt === null ? $booking->starts_at->copy()->subHour() : null,
                        'created_at' => $createdAt,
                        'updated_at' => $decidedAt ?? $createdAt,
                    ]);
                };

                $approval($bookings['completed_room'], 1, $dewi, 'approved', $at(-5, 8), 'Disetujui.');
                $approval($bookings['no_show_room'], 1, $dewi, 'approved', $at(-4, 11), 'Disetujui.');
                $approval($bookings['checked_in_room'], 1, $dewi, 'approved', $at(-1, 15), 'Disetujui.');
                $approval($bookings['approved_room'], 1, $dewi, 'approved', $at(-1, 15), 'Disetujui, silakan digunakan.');
                $approval($bookings['pending_room'], 1, $dewi, 'pending', null);

                $approval($bookings['rejected_room'], 1, $dewi, 'rejected', $at(-1, 9), 'Bentrok dengan agenda institusi.');

                $approval($bookings['completed_camera'], 1, $dewi, 'approved', $at(-8, 10), 'Disetujui level 1.');
                $approval($bookings['completed_camera'], 2, $rina, 'approved', $at(-7, 8), 'Disetujui level 2.', $at(-8, 10));

                $approval($bookings['in_use_projector'], 1, $dewi, 'approved', $at(-1, 10), 'Disetujui.');

                $approval($bookings['reserved_camera'], 1, $dewi, 'approved', $at(-1, 10), 'Disetujui level 1.');
                $approval($bookings['reserved_camera'], 2, $rina, 'approved', $at(-1, 13), 'Disetujui level 2.', $at(-1, 10));

                $approval($bookings['pending_camera'], 1, $dewi, 'approved', $now->copy()->subHours(2), 'Disetujui level 1, menunggu level 2.', $now->copy()->subHours(3));
                $approval($bookings['pending_camera'], 2, $rina, 'pending', null, null, $now->copy()->subHours(2));

                $checkIn = function (Booking $booking, Carbon $at, CheckInResult|string $result, array $extra = []) {
                    return $booking->checkInAttempts()->create([
                        'user_id' => $booking->user_id,
                        'result' => $result,
                        'created_at' => $at,
                        'updated_at' => $at,
                    ] + $extra);
                };

                $completedRoomAttempt = $checkIn($bookings['completed_room'], $at(-5, 8, 55), CheckInResult::ACCEPTED, [
                    'qr_valid' => true,
                    'gps_valid' => true,
                    'face_valid' => true,
                    'gps_latitude' => $room1Loc?->latitude ?? -6.2088000,
                    'gps_longitude' => $room1Loc?->longitude ?? 106.8456000,
                    'gps_accuracy' => 8.5,
                    'gps_timestamp' => $at(-5, 8, 55),
                    'face_score' => 0.9310,
                    'liveness_passed' => true,
                ]);

                FaceVerification::create([
                    'user_id' => $budi->id,
                    'check_in_attempt_id' => $completedRoomAttempt->id,
                    'score' => 0.9310,
                    'liveness_passed' => true,
                    'challenge_type' => 'blink',
                    'matched' => true,
                    'created_at' => $at(-5, 8, 55),
                    'updated_at' => $at(-5, 8, 55),
                ]);

                $completedLabAttempt = $checkIn($bookings['completed_lab'], $at(-2, 9, 58), CheckInResult::ACCEPTED, [
                    'qr_valid' => true,
                    'gps_valid' => true,
                    'face_valid' => true,
                    'gps_latitude' => $room2Loc?->latitude ?? -6.2092000,
                    'gps_longitude' => $room2Loc?->longitude ?? 106.8460000,
                    'gps_accuracy' => 12.0,
                    'gps_timestamp' => $at(-2, 9, 58),
                    'face_score' => 0.8840,
                    'liveness_passed' => true,
                ]);

                FaceVerification::create([
                    'user_id' => $sari->id,
                    'check_in_attempt_id' => $completedLabAttempt->id,
                    'score' => 0.8840,
                    'liveness_passed' => true,
                    'challenge_type' => 'smile',
                    'matched' => true,
                    'created_at' => $at(-2, 9, 58),
                    'updated_at' => $at(-2, 9, 58),
                ]);

                $checkedInAttempt = $checkIn($bookings['checked_in_room'], $now->copy()->subMinutes(25), CheckInResult::ACCEPTED, [
                    'qr_valid' => true,
                    'gps_valid' => true,
                    'face_valid' => true,
                    'gps_latitude' => $room1Loc?->latitude ?? -6.2088000,
                    'gps_longitude' => $room1Loc?->longitude ?? 106.8456000,
                    'gps_accuracy' => 6.2,
                    'gps_timestamp' => $now->copy()->subMinutes(25),
                    'face_score' => 0.9120,
                    'liveness_passed' => true,
                ]);

                FaceVerification::create([
                    'user_id' => $agus->id,
                    'check_in_attempt_id' => $checkedInAttempt->id,
                    'score' => 0.9120,
                    'liveness_passed' => true,
                    'challenge_type' => 'blink',
                    'matched' => true,
                    'created_at' => $now->copy()->subMinutes(25),
                    'updated_at' => $now->copy()->subMinutes(25),
                ]);

                $checkIn($bookings['checked_in_room'], $now->copy()->subMinutes(28), CheckInResult::REJECTED, [
                    'qr_valid' => true,
                    'gps_valid' => false,
                    'face_valid' => true,
                    'gps_latitude' => -6.2300000,
                    'gps_longitude' => 106.8700000,
                    'gps_accuracy' => 45.0,
                    'gps_timestamp' => $now->copy()->subMinutes(28),
                    'face_score' => 0.9050,
                    'liveness_passed' => true,
                    'rejection_reasons' => ['gps_out_of_radius'],
                ]);

                $inUseAttempt = $checkIn($bookings['in_use_projector'], $now->copy()->subMinutes(115), CheckInResult::NEEDS_MANUAL_REVIEW, [
                    'qr_valid' => true,
                    'gps_valid' => true,
                    'face_valid' => false,
                    'gps_latitude' => $asset1Resource->location?->latitude,
                    'gps_longitude' => $asset1Resource->location?->longitude,
                    'gps_accuracy' => 9.0,
                    'gps_timestamp' => $now->copy()->subMinutes(115),
                    'face_score' => 0.6100,
                    'liveness_passed' => true,
                    'rejection_reasons' => ['face_low_similarity'],
                    'override_by' => $manager->id,
                    'override_reason' => 'Identitas diverifikasi manual oleh resource manager.',
                ]);

                FaceVerification::create([
                    'user_id' => $agus->id,
                    'check_in_attempt_id' => $inUseAttempt->id,
                    'score' => 0.6100,
                    'liveness_passed' => true,
                    'challenge_type' => 'turn_head',
                    'matched' => false,
                    'created_at' => $now->copy()->subMinutes(115),
                    'updated_at' => $now->copy()->subMinutes(115),
                ]);

                WaitlistEntry::create([
                    'user_id' => $agus->id,
                    'resource_id' => $room1->id,
                    'desired_start' => $at(1, 9),
                    'desired_end' => $at(1, 11),
                    'offered_at' => null,
                    'offer_expires_at' => null,
                    'claimed_at' => null,
                    'status' => 'waiting',
                    'created_at' => $now->copy()->subHour(),
                    'updated_at' => $now->copy()->subHour(),
                ]);

                $completedLoan = AssetLoan::create([
                    'booking_id' => $bookings['completed_camera']->id,
                    'asset_id' => $asset2->id,
                    'user_id' => $budi->id,
                    'status' => LoanStatus::CLOSED,
                    'handed_over_at' => $at(-7, 8, 5),
                    'returned_at' => $at(-7, 16, 50),
                    'due_at' => $at(-7, 17),
                ] + $timestamps($at(-7, 8)));

                LoanEvent::create([
                    'asset_loan_id' => $completedLoan->id,
                    'event_type' => 'handed_over',
                    'actor_id' => $manager->id,
                    'data' => ['note' => 'Kamera diserahkan lengkap dengan tas dan lensa kit.'],
                    'created_at' => $at(-7, 8, 5),
                    'updated_at' => $at(-7, 8, 5),
                ]);

                LoanEvent::create([
                    'asset_loan_id' => $completedLoan->id,
                    'event_type' => 'returned',
                    'actor_id' => $budi->id,
                    'data' => ['note' => 'Dikembalikan tepat waktu.'],
                    'created_at' => $at(-7, 16, 50),
                    'updated_at' => $at(-7, 16, 50),
                ]);

                LoanEvent::create([
                    'asset_loan_id' => $completedLoan->id,
                    'event_type' => 'closed',
                    'actor_id' => $manager->id,
                    'data' => ['note' => 'Peminjaman selesai dan asset tersedia kembali.'],
                    'created_at' => $at(-7, 17, 0),
                    'updated_at' => $at(-7, 17, 0),
                ]);

                ConditionReport::create([
                    'asset_loan_id' => $completedLoan->id,
                    'reporter_id' => $budi->id,
                    'condition' => 'good',
                    'notes' => 'Lensa lecet ringan, fungsi kamera normal.',
                    'photo_path' => 'condition-reports/dummy-camera-return.jpg',
                    'created_at' => $at(-7, 16, 55),
                    'updated_at' => $at(-7, 16, 55),
                ]);

                $inUseLoan = AssetLoan::create([
                    'booking_id' => $bookings['in_use_projector']->id,
                    'asset_id' => $asset1->id,
                    'user_id' => $agus->id,
                    'status' => LoanStatus::IN_USE,
                    'handed_over_at' => $now->copy()->subHours(2),
                    'returned_at' => null,
                    'due_at' => $now->copy()->addHours(2),
                ] + $timestamps($now->copy()->subHours(2)));

                LoanEvent::create([
                    'asset_loan_id' => $inUseLoan->id,
                    'event_type' => 'handed_over',
                    'actor_id' => $manager->id,
                    'data' => ['note' => 'Proyektor diserahkan beserta kabel HDMI.'],
                    'created_at' => $now->copy()->subHours(2),
                    'updated_at' => $now->copy()->subHours(2),
                ]);

                LoanEvent::create([
                    'asset_loan_id' => $inUseLoan->id,
                    'event_type' => 'in_use',
                    'actor_id' => $agus->id,
                    'data' => ['note' => 'Sedang digunakan untuk rapat.'],
                    'created_at' => $now->copy()->subMinutes(110),
                    'updated_at' => $now->copy()->subMinutes(110),
                ]);

                AssetLoan::create([
                    'booking_id' => $bookings['reserved_camera']->id,
                    'asset_id' => $asset2->id,
                    'user_id' => $sari->id,
                    'status' => LoanStatus::RESERVED,
                    'handed_over_at' => null,
                    'returned_at' => null,
                    'due_at' => $at(2, 12),
                ] + $timestamps($at(-1, 13)));

                Strike::create([
                    'user_id' => $joko->id,
                    'reason' => 'no_show',
                    'source_type' => Booking::class,
                    'source_id' => $bookings['no_show_room']->id,
                    'created_at' => $at(-3, 15, 30),
                    'updated_at' => $at(-3, 15, 30),
                ]);

                Strike::create([
                    'user_id' => $joko->id,
                    'reason' => 'late_cancellation',
                    'source_type' => null,
                    'source_id' => null,
                    'created_at' => $at(-20, 10),
                    'updated_at' => $at(-20, 10),
                ]);

                Strike::create([
                    'user_id' => $joko->id,
                    'reason' => 'no_show',
                    'source_type' => null,
                    'source_id' => null,
                    'created_at' => $at(-10, 14),
                    'updated_at' => $at(-10, 14),
                ]);

                Strike::create([
                    'user_id' => $sari->id,
                    'reason' => 'no_show',
                    'source_type' => null,
                    'source_id' => null,
                    'cancelled_at' => $at(-5, 9),
                    'cancelled_by' => $admin->id,
                    'cancel_reason' => 'Kesalahan sistem, dibatalkan oleh admin.',
                    'created_at' => $at(-6, 10),
                    'updated_at' => $at(-5, 9),
                ]);

                Suspension::create([
                    'user_id' => $joko->id,
                    'starts_at' => $at(-1, 0),
                    'ends_at' => $at(6, 23, 59),
                    'reason' => 'Akumulasi 3 strike dalam 30 hari terakhir.',
                ] + $timestamps($at(-1, 0)));

                foreach ([$budi, $agus, $sari] as $idx => $user) {
                    FaceEnrollment::create([
                        'user_id' => $user->id,
                        'consent_given_at' => $now->copy()->subDays(20 - $idx),
                        'status' => 'active',
                        'samples_count' => 5,
                        'embedding_reference' => 'face-embed-'.$user->id,
                    ] + $timestamps($now->copy()->subDays(20 - $idx)));
                }

                DeviceToken::create([
                    'user_id' => $budi->id,
                    'token' => 'dummy-device-token-'.Str::random(32),
                    'platform' => 'android',
                    'last_seen_at' => $now->copy()->subMinutes(10),
                ] + $timestamps($now->copy()->subDays(5)));

                DeviceToken::create([
                    'user_id' => $agus->id,
                    'token' => 'dummy-device-token-'.Str::random(32),
                    'platform' => 'ios',
                    'last_seen_at' => $now->copy()->subMinutes(2),
                ] + $timestamps($now->copy()->subDays(3)));

                $notify = function (User $user, string $type, string $channel, array $data, string $status, ?Carbon $sentAt, ?string $error = null, ?Carbon $createdAt = null) use ($timestamps) {
                    $createdAt ??= $sentAt ?? Carbon::now();
                    NotificationLog::create([
                        'user_id' => $user->id,
                        'type' => $type,
                        'channel' => $channel,
                        'data' => $data,
                        'status' => $status,
                        'sent_at' => $sentAt,
                        'error' => $error,
                    ] + $timestamps($createdAt));
                };

                $notify($budi, 'booking_approved', 'push', ['booking_id' => $bookings['approved_room']->id], 'sent', $at(-1, 15, 1));
                $notify($dewi, 'approval_requested', 'push', ['booking_id' => $bookings['pending_room']->id], 'pending', null, null, $now->copy()->subHours(2));
                $notify($agus, 'waitlist_waiting', 'push', ['resource_id' => $room1->id], 'sent', $now->copy()->subMinutes(55));
                $notify($joko, 'suspension_active', 'push', ['until' => $at(6, 23, 59)->toIso8601String()], 'sent', $at(-1, 0, 1));
                $notify($sari, 'booking_reminder', 'push', ['booking_id' => $bookings['reserved_camera']->id], 'failed', null, 'Token perangkat tidak valid.', $at(-1, 13, 5));

                $this->command?->info('Data dummy alur kerja berhasil dibuat: '.count($bookings).' booking, approval, check-in, pinjaman asset, strike, suspensi, dan notifikasi.');
            });
        } finally {
            Model::reguard();
        }
    }
}
