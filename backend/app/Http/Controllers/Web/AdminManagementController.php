<?php

namespace App\Http\Controllers\Web;

use App\Enums\BookingStatus;
use App\Enums\LoanStatus;
use App\Enums\Role as RoleEnum;
use App\Http\Controllers\Controller;
use App\Models\AssetLoan;
use App\Models\Booking;
use App\Models\CheckInAttempt;
use App\Models\ConditionReport;
use App\Models\Department;
use App\Models\LoanEvent;
use App\Models\Location;
use App\Models\Resource;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Strike;
use App\Models\Suspension;
use App\Models\User;
use App\Services\PenaltyService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminManagementController extends Controller
{
    public function locations()
    {
        $this->ensureAdmin();

        $locations = Location::withCount('resources')->orderBy('name')->get();

        return view('admin.locations', compact('locations'));
    }

    public function storeLocation(Request $request)
    {
        $this->ensureAdmin();

        Location::create($this->locationPayload($request));

        return back()->with('success', 'Lokasi ditambahkan.');
    }

    public function updateLocation(Request $request, Location $location)
    {
        $this->ensureAdmin();

        $location->update($this->locationPayload($request));

        return back()->with('success', 'Lokasi diperbarui.');
    }

    public function destroyLocation(Location $location)
    {
        $this->ensureAdmin();

        if ($location->resources()->exists()) {
            return back()->withErrors(['location' => 'Lokasi masih dipakai resource. Pindahkan resource terlebih dahulu.']);
        }

        $location->delete();

        return back()->with('success', 'Lokasi dihapus.');
    }

    public function settings()
    {
        $this->ensureAdmin();

        $definitions = collect($this->settingDefinitions())
            ->map(fn (array $definition, string $key) => ['key' => $key] + $definition)
            ->all();

        $values = collect($definitions)->mapWithKeys(
            fn (array $definition) => [$definition['key'] => Setting::get($definition['key'], $definition['default'])],
        )->all();

        return view('admin.settings', compact('definitions', 'values'));
    }

    public function updateSettings(Request $request)
    {
        $this->ensureAdmin();

        $definitions = $this->settingDefinitions();

        $rules = [];
        foreach ($definitions as $key => $definition) {
            $rules["settings.{$key}"] = match ($definition['type']) {
                'boolean' => ['nullable', 'boolean'],
                'integer' => ['nullable', 'integer', 'min:0'],
                'float' => ['nullable', 'numeric'],
                default => ['nullable', 'string'],
            };
        }

        $request->validate($rules);

        DB::transaction(function () use ($definitions, $request) {
            foreach ($definitions as $key => $definition) {
                $raw = $request->input("settings.{$key}");
                $value = match ($definition['type']) {
                    'boolean' => $request->boolean("settings.{$key}"),
                    'integer' => (int) ($raw ?? $definition['default']),
                    'float' => (float) ($raw ?? $definition['default']),
                    default => (string) ($raw ?? ''),
                };

                Setting::set($key, $value, $definition['type']);
            }
        });

        return back()->with('success', 'Pengaturan disimpan.');
    }

    public function users(Request $request)
    {
        $this->ensureAdmin();

        $query = User::with(['roles', 'department'])->latest();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->paginate(20)->withQueryString();
        $roles = Role::orderBy('name')->get();
        $departments = Department::orderBy('name')->get();
        $roleLabels = $this->roleLabels();

        return view('admin.users', compact('users', 'roles', 'departments', 'roleLabels'));
    }

    public function storeUser(Request $request)
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'role_id' => ['required', 'exists:roles,id'],
        ]);

        DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'department_id' => $validated['department_id'] ?? null,
            ]);

            $user->roles()->sync([$validated['role_id']]);
        });

        return back()->with('success', 'Pengguna ditambahkan.');
    }

    public function updateUser(Request $request, User $user)
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'role_id' => ['required', 'exists:roles,id'],
        ]);

        $adminRoleId = Role::where('name', RoleEnum::ADMIN->value)->value('id');

        if ($user->is(auth()->user()) && (int) $validated['role_id'] !== (int) $adminRoleId) {
            return back()->withErrors(['role_id' => 'Anda tidak bisa menurunkan peran akun sendiri.']);
        }

        DB::transaction(function () use ($validated, $user) {
            $user->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'department_id' => $validated['department_id'] ?? null,
                ...($validated['password'] ? ['password' => $validated['password']] : []),
            ]);

            $user->roles()->sync([$validated['role_id']]);
        });

        return back()->with('success', 'Pengguna diperbarui.');
    }

    public function penalties()
    {
        $this->ensureAdmin();

        $strikes = Strike::with(['user', 'cancelledBy'])
            ->latest()
            ->paginate(15, ['*'], 'strikes_page')
            ->withQueryString();

        $suspensions = Suspension::with('user')
            ->latest()
            ->paginate(15, ['*'], 'suspensions_page')
            ->withQueryString();

        $users = User::orderBy('name')->get();

        return view('admin.penalties', compact('strikes', 'suspensions', 'users'));
    }

    public function storeStrike(Request $request, PenaltyService $penaltyService)
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $penaltyService->issueStrike(User::findOrFail($validated['user_id']), $validated['reason']);

        return back()->with('success', 'Strike dicatat.');
    }

    public function cancelStrike(Request $request, Strike $strike, PenaltyService $penaltyService)
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $penaltyService->cancelStrike($strike, $request->user(), $validated['reason']);

        return back()->with('success', 'Strike dibatalkan.');
    }

    public function storeSuspension(Request $request)
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'reason' => ['nullable', 'string'],
        ]);

        Suspension::create($validated);

        return back()->with('success', 'Suspensi dibuat.');
    }

    public function endSuspension(Suspension $suspension)
    {
        $this->ensureAdmin();

        $suspension->update(['ends_at' => now()]);

        return back()->with('success', 'Suspensi diakhiri.');
    }

    public function bookings()
    {
        $this->ensureCanManageLoans();

        $now = now();
        $todayStart = $now->copy()->startOfDay();
        $todayEnd = $now->copy()->endOfDay();

        $activeRoomBookings = Booking::with(['user.department', 'resource.location'])
            ->whereHas('resource', fn ($query) => $query->where('type', 'room'))
            ->whereIn('status', [BookingStatus::APPROVED->value, BookingStatus::CHECKED_IN->value])
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>=', $now)
            ->orderBy('ends_at')
            ->get();

        $queuedRoomBookings = Booking::with(['user.department', 'resource.location'])
            ->whereHas('resource', fn ($query) => $query->where('type', 'room'))
            ->whereIn('status', [BookingStatus::APPROVED->value, BookingStatus::CHECKED_IN->value])
            ->where('starts_at', '>', $now)
            ->whereBetween('starts_at', [$todayStart, $todayEnd])
            ->orderBy('starts_at')
            ->get();

        $activeLoans = AssetLoan::with(['asset.resource.location', 'booking', 'user.department'])
            ->whereIn('status', [LoanStatus::HANDED_OVER->value, LoanStatus::IN_USE->value])
            ->orderBy('due_at')
            ->get();

        $queuedLoans = AssetLoan::with(['asset.resource.location', 'booking', 'user.department'])
            ->where('status', LoanStatus::RESERVED->value)
            ->whereHas('booking', fn ($query) => $query->whereBetween('starts_at', [$todayStart, $todayEnd]))
            ->orderBy('due_at')
            ->get();

        $queuedEntries = $queuedRoomBookings
            ->map(fn (Booking $booking) => [
                'type' => 'Ruangan',
                'title' => $booking->resource->name ?? 'Ruangan tidak dikenal',
                'subtitle' => $booking->resource->location->name ?? '—',
                'user' => $booking->user,
                'starts_at' => $booking->starts_at,
                'ends_at' => $booking->ends_at,
                'status' => $booking->status->value === 'checked_in' ? 'Check-in' : 'Disetujui',
                'status_tone' => $booking->status->value === 'checked_in' ? 'tag--amber' : 'tag--plain',
            ])
            ->concat($queuedLoans->map(fn (AssetLoan $loan) => [
                'type' => 'Aset',
                'title' => $loan->asset->resource->name ?? 'Aset tidak dikenal',
                'subtitle' => $loan->asset->code ?? '—',
                'user' => $loan->user,
                'starts_at' => $loan->booking?->starts_at,
                'ends_at' => $loan->booking?->ends_at,
                'status' => 'Dipesan',
                'status_tone' => 'tag--plain',
            ]))
            ->sortBy(fn (array $entry) => $entry['starts_at']?->timestamp ?? PHP_INT_MAX)
            ->values();

        $totalRooms = Resource::where('type', 'room')->where('is_active', true)->count();
        $totalAssets = Resource::where('type', 'asset')->where('is_active', true)->count();

        return view('admin.bookings', compact(
            'activeRoomBookings',
            'activeLoans',
            'queuedEntries',
            'totalRooms',
            'totalAssets',
            'now',
        ));
    }

    public function loans(Request $request)
    {
        $this->ensureCanManageLoans();

        $query = AssetLoan::with(['asset.resource', 'booking', 'user'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $loans = $query->paginate(20)->withQueryString();

        return view('admin.loans', compact('loans'));
    }

    public function handoverLoan(Request $request, AssetLoan $loan)
    {
        $this->ensureCanManageLoans();

        if ($loan->status !== LoanStatus::RESERVED) {
            return back()->withErrors(['loan' => 'Aset tidak berada pada status siap diserahkan.']);
        }

        DB::transaction(function () use ($loan, $request) {
            $loan->update([
                'status' => LoanStatus::HANDED_OVER,
                'handed_over_at' => now(),
            ]);

            $loan->asset->update(['status' => 'in_use']);

            LoanEvent::create([
                'asset_loan_id' => $loan->id,
                'event_type' => 'handover',
                'actor_id' => $request->user()->id,
                'data' => ['source' => 'admin_web'],
            ]);
        });

        return back()->with('success', 'Aset ditandai sudah diserahkan.');
    }

    public function returnLoan(Request $request, AssetLoan $loan)
    {
        $this->ensureCanManageLoans();

        if (! in_array($loan->status, [LoanStatus::HANDED_OVER, LoanStatus::IN_USE], true)) {
            return back()->withErrors(['loan' => 'Aset tidak sedang dipinjam.']);
        }

        $validated = $request->validate([
            'condition' => ['required', 'in:good,damaged,lost,maintenance'],
            'notes' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($loan, $request, $validated) {
            $loan->update([
                'status' => LoanStatus::RETURNED,
                'returned_at' => now(),
            ]);

            ConditionReport::create([
                'asset_loan_id' => $loan->id,
                'reporter_id' => $request->user()->id,
                'condition' => $validated['condition'],
                'notes' => $validated['notes'] ?? null,
            ]);

            LoanEvent::create([
                'asset_loan_id' => $loan->id,
                'event_type' => 'return',
                'actor_id' => $request->user()->id,
                'data' => ['condition' => $validated['condition'], 'source' => 'admin_web'],
            ]);

            $loan->asset->update([
                'condition' => $validated['condition'],
                'status' => 'available',
            ]);
        });

        return back()->with('success', 'Aset ditandai sudah dikembalikan.');
    }

    public function reports(Request $request)
    {
        $this->ensureAdmin();

        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = Carbon::parse($request->input('from', now()->startOfMonth()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->input('to', now()->endOfMonth()->toDateString()))->endOfDay();

        $bookings = Booking::whereBetween('starts_at', [$from, $to]);

        $totalBookings = (clone $bookings)->count();
        $completedBookings = (clone $bookings)->where('status', BookingStatus::COMPLETED->value)->count();
        $noShowBookings = (clone $bookings)->where('status', BookingStatus::NO_SHOW->value)->count();
        $noShowRate = $totalBookings > 0 ? round($noShowBookings / $totalBookings * 100, 1) : 0;

        $roomUtilization = Booking::with('resource')
            ->where('status', BookingStatus::COMPLETED->value)
            ->whereBetween('starts_at', [$from, $to])
            ->whereHas('resource', fn ($query) => $query->where('type', 'room'))
            ->get()
            ->groupBy('resource_id')
            ->map(function ($items) {
                return [
                    'name' => $items->first()->resource->name ?? 'Resource',
                    'bookings' => $items->count(),
                    'minutes' => (int) $items->sum(fn ($booking) => $booking->starts_at->diffInMinutes($booking->ends_at)),
                ];
            })
            ->sortByDesc('minutes')
            ->values();

        $popularAssets = AssetLoan::with('asset.resource')
            ->select('asset_id', DB::raw('COUNT(*) as loan_count'))
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('asset_id')
            ->orderByDesc('loan_count')
            ->limit(8)
            ->get();

        $attempts = CheckInAttempt::whereBetween('created_at', [$from, $to]);
        $totalAttempts = (clone $attempts)->count();
        $qrPass = (clone $attempts)->where('qr_valid', true)->count();
        $gpsPass = (clone $attempts)->where('gps_valid', true)->count();
        $facePass = (clone $attempts)->where('face_valid', true)->count();

        $verification = [
            'total_attempts' => $totalAttempts,
            'qr_success' => $totalAttempts > 0 ? round($qrPass / $totalAttempts * 100, 1) : 0,
            'gps_success' => $totalAttempts > 0 ? round($gpsPass / $totalAttempts * 100, 1) : 0,
            'face_success' => $totalAttempts > 0 ? round($facePass / $totalAttempts * 100, 1) : 0,
        ];

        $fromInput = $from->toDateString();
        $toInput = $to->toDateString();

        return view('admin.reports', compact(
            'fromInput',
            'toInput',
            'totalBookings',
            'completedBookings',
            'noShowBookings',
            'noShowRate',
            'roomUtilization',
            'popularAssets',
            'verification',
        ));
    }

    private function locationPayload(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'geofence_type' => ['required', 'in:radius,polygon'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius' => ['required_if:geofence_type,radius', 'nullable', 'integer', 'min:10'],
            'polygon' => ['nullable', 'string'],
        ]);

        $type = $data['geofence_type'];
        unset($data['geofence_type']);

        if ($type === 'polygon') {
            $points = json_decode((string) ($data['polygon'] ?? ''), true);

            if (! is_array($points) || count($points) < 3) {
                throw ValidationException::withMessages([
                    'polygon' => 'Polygon membutuhkan minimal 3 titik. Klik peta untuk menambahkan titik.',
                ]);
            }

            foreach ($points as $index => $point) {
                $lat = is_array($point) ? ($point['lat'] ?? null) : null;
                $lng = is_array($point) ? ($point['lng'] ?? null) : null;

                if (! is_numeric($lat) || ! is_numeric($lng)
                    || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                    throw ValidationException::withMessages([
                        'polygon' => 'Titik polygon ke-'.($index + 1).' tidak valid.',
                    ]);
                }
            }

            $data['polygon'] = collect($points)
                ->map(fn (array $point) => ['lat' => (float) $point['lat'], 'lng' => (float) $point['lng']])
                ->all();
            $data['radius'] = (int) ($data['radius'] ?? 100);
        } else {
            $data['polygon'] = null;
            $data['radius'] = (int) ($data['radius'] ?? 100);
        }

        return $data;
    }

    private function settingDefinitions(): array
    {
        return [
            'checkin_window_before_minutes' => ['group' => 'Check-in & verifikasi', 'label' => 'Jendela check-in sebelum jadwal', 'type' => 'integer', 'default' => 15, 'hint' => 'Menit sebelum waktu mulai.'],
            'checkin_window_after_minutes' => ['group' => 'Check-in & verifikasi', 'label' => 'Jendela check-in setelah jadwal', 'type' => 'integer', 'default' => 15, 'hint' => 'Menit setelah waktu mulai.'],
            'check_in_window_minutes' => ['group' => 'Check-in & verifikasi', 'label' => 'Batas check-in untuk no-show', 'type' => 'integer', 'default' => 15, 'hint' => 'Dipakai job penanda no-show.'],
            'face_verification_enabled' => ['group' => 'Check-in & verifikasi', 'label' => 'Wajib verifikasi wajah', 'type' => 'boolean', 'default' => false, 'hint' => 'Aktifkan hanya jika face-service siap.'],
            'checkin_failure_strike_threshold' => ['group' => 'Check-in & verifikasi', 'label' => 'Ambang strike gagal check-in', 'type' => 'integer', 'default' => 3, 'hint' => 'Jumlah gagal sebelum strike.'],
            'gps_accuracy_threshold_meters' => ['group' => 'GPS & QR', 'label' => 'Akurasi GPS maksimum (meter)', 'type' => 'integer', 'default' => 50, 'hint' => 'Nilai akurasi yang masih diterima.'],
            'gps_freshness_seconds' => ['group' => 'GPS & QR', 'label' => 'Umur data GPS maksimum (detik)', 'type' => 'integer', 'default' => 30, 'hint' => 'Data GPS lebih lama akan ditolak.'],
            'gps_max_speed_mps' => ['group' => 'GPS & QR', 'label' => 'Kecepatan perpindahan maksimum (m/s)', 'type' => 'integer', 'default' => 100, 'hint' => 'Deteksi lompatan lokasi.'],
            'qr_token_ttl_seconds' => ['group' => 'GPS & QR', 'label' => 'Masa berlaku QR (detik)', 'type' => 'integer', 'default' => 30, 'hint' => 'Token QR berganti otomatis.'],
            'approval_expiry_hours' => ['group' => 'Persetujuan & waitlist', 'label' => 'Batas waktu persetujuan (jam)', 'type' => 'integer', 'default' => 48, 'hint' => 'Approval yang lewat batas akan kedaluwarsa.'],
            'approval_escalate_on_expiry' => ['group' => 'Persetujuan & waitlist', 'label' => 'Naikkan ulang saat kedaluwarsa', 'type' => 'boolean', 'default' => false, 'hint' => 'Alihkan approval ke periode berikutnya.'],
            'waitlist_offer_minutes' => ['group' => 'Persetujuan & waitlist', 'label' => 'Batas tawaran waitlist (menit)', 'type' => 'integer', 'default' => 30, 'hint' => 'Waktu pengguna menerima slot kosong.'],
            'strike_window_days' => ['group' => 'Penalti', 'label' => 'Jendela hitung strike (hari)', 'type' => 'integer', 'default' => 30, 'hint' => 'Strike di luar jendela tidak dihitung.'],
            'strike_threshold' => ['group' => 'Penalti', 'label' => 'Ambang strike untuk suspensi', 'type' => 'integer', 'default' => 3, 'hint' => 'Jumlah strike pemicu suspensi otomatis.'],
            'suspension_duration_days' => ['group' => 'Penalti', 'label' => 'Durasi suspensi otomatis (hari)', 'type' => 'integer', 'default' => 7, 'hint' => 'Berlaku untuk suspensi otomatis.'],
        ];
    }

    private function roleLabels(): array
    {
        return [
            RoleEnum::ADMIN->value => 'Admin',
            RoleEnum::APPROVER->value => 'Approver',
            RoleEnum::RESOURCE_MANAGER->value => 'Pengelola resource',
            RoleEnum::MEMBER->value => 'Anggota',
        ];
    }

    private function ensureAdmin(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Hanya admin yang bisa mengakses halaman ini.');
    }

    private function ensureCanManageLoans(): void
    {
        abort_unless(
            auth()->user()->isAdmin() || auth()->user()->hasRole(RoleEnum::RESOURCE_MANAGER->value),
            403,
            'Hanya admin atau pengelola resource yang bisa mengelola peminjaman.',
        );
    }
}
