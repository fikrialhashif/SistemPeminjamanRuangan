<?php

namespace App\Http\Controllers\Web;

use App\Enums\BookingStatus;
use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Approval;
use App\Models\Asset;
use App\Models\AssetLoan;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Category;
use App\Models\Facility;
use App\Models\Location;
use App\Models\Resource;
use App\Models\Strike;
use App\Models\User;
use App\Services\ApprovalService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    private const DASHBOARD_PERIODS = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];

    /**
     * @var array<string, int>
     */
    private const DASHBOARD_CHART_DEFAULTS = [
        'booking_trend' => 6,
        'booking_status' => 6,
        'top_rooms' => 6,
        'approval_trend' => 6,
        'approval_decisions' => 6,
        'approval_queue' => 6,
        'room_usage' => 6,
        'popular_assets' => 6,
    ];

    public function showLoginForm()
    {
        return view('admin.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $user = Auth::user();
            $allowedRoles = [Role::ADMIN->value, Role::APPROVER->value, Role::RESOURCE_MANAGER->value];

            $hasRole = $user->roles()->whereIn('name', $allowedRoles)->exists();

            if (! $hasRole) {
                Auth::logout();

                return back()->withErrors(['email' => 'Akun ini tidak punya akses ke panel admin.']);
            }

            $request->session()->regenerate();

            return redirect()->route('admin.dashboard');
        }

        return back()->withErrors(['email' => 'Email atau kata sandi salah.'])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    public function dashboard(Request $request)
    {
        $periods = $this->dashboardPeriods($request);

        return view('admin.dashboard', [
            'totalBookings' => Booking::count(),
            'pendingApprovals' => Approval::awaitingDecision()->count(),
            'activeResources' => Resource::where('is_active', true)->count(),
            'totalStrikes' => Strike::count(),
            'recentApprovals' => Approval::awaitingDecision()
                ->with(['booking.user', 'booking.resource'])
                ->latest()
                ->limit(4)
                ->get(),
            'charts' => $this->dashboardCharts($request->user(), $periods),
            'periods' => $periods,
            'periodOptions' => self::DASHBOARD_PERIODS,
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function dashboardPeriods(Request $request): array
    {
        $input = $request->input('periods', []);
        $input = is_array($input) ? $input : [];

        $periods = [];

        foreach (self::DASHBOARD_CHART_DEFAULTS as $key => $default) {
            $months = (int) ($input[$key] ?? $default);
            $periods[$key] = in_array($months, self::DASHBOARD_PERIODS, true) ? $months : $default;
        }

        return $periods;
    }

    /**
     * @param  array<string, int>  $periods
     * @return list<array<string, mixed>>
     */
    private function dashboardCharts(User $user, array $periods): array
    {
        if ($user->isAdmin()) {
            return [
                $this->monthlyBookingTrend($periods['booking_trend']),
                $this->bookingStatusChart($periods['booking_status']),
                $this->topRoomsChart($periods['top_rooms']),
            ];
        }

        $charts = [];

        if ($user->hasPermission(Permission::APPROVALS_VIEW)) {
            $charts[] = $this->monthlyApprovalTrend($periods['approval_trend']);
            $charts[] = $this->approvalDecisionChart($periods['approval_decisions']);
            $charts[] = $this->pendingApprovalsByRoomChart($periods['approval_queue']);
        }

        if ($user->hasPermission(Permission::BOOKINGS_VIEW) || $user->hasPermission(Permission::RESOURCES_VIEW)) {
            $charts[] = $this->roomUtilizationChart($periods['room_usage']);

            if ($user->hasPermission(Permission::LOANS_VIEW)) {
                $charts[] = $this->popularAssetsChart($periods['popular_assets']);
            }

            $charts[] = $this->assetConditionChart();
        }

        return $charts;
    }

    /**
     * @return array<string, mixed>
     */
    private function monthlyBookingTrend(int $months): array
    {
        $counts = Booking::where('starts_at', '>=', $this->dashboardMonthStart($months)->utc())
            ->select('starts_at')
            ->get()
            ->countBy(fn (Booking $booking) => $booking->starts_at->wib()->format('Y-m'));

        $items = $this->dashboardMonths($months)->map(fn (Carbon $month) => [
            'label' => $month->locale('id')->isoFormat('MMM'),
            'value' => (int) ($counts[$month->format('Y-m')] ?? 0),
        ]);

        return ['key' => 'booking_trend', ...$this->barChart('Tren peminjaman', "{$months} bulan terakhir", $items)];
    }

    /**
     * @return array<string, mixed>
     */
    private function monthlyApprovalTrend(int $months): array
    {
        $counts = Approval::where('created_at', '>=', $this->dashboardMonthStart($months)->utc())
            ->select('created_at')
            ->get()
            ->countBy(fn (Approval $approval) => $approval->created_at->wib()->format('Y-m'));

        $items = $this->dashboardMonths($months)->map(fn (Carbon $month) => [
            'label' => $month->locale('id')->isoFormat('MMM'),
            'value' => (int) ($counts[$month->format('Y-m')] ?? 0),
        ]);

        return ['key' => 'approval_trend', ...$this->barChart('Pengajuan persetujuan', "{$months} bulan terakhir", $items)];
    }

    /**
     * @return array<string, mixed>
     */
    private function bookingStatusChart(int $months): array
    {
        $labels = [
            BookingStatus::PENDING->value => 'Menunggu',
            BookingStatus::APPROVED->value => 'Disetujui',
            BookingStatus::CHECKED_IN->value => 'Check-in',
            BookingStatus::COMPLETED->value => 'Selesai',
            BookingStatus::REJECTED->value => 'Ditolak',
            BookingStatus::CANCELLED->value => 'Dibatalkan',
            BookingStatus::EXPIRED->value => 'Kedaluwarsa',
            BookingStatus::NO_SHOW->value => 'Tidak hadir',
            BookingStatus::WAITLISTED->value => 'Antrian',
        ];
        $counts = Booking::whereBetween('starts_at', [$this->dashboardPeriodStart($months), now()])
            ->select('status')
            ->get()
            ->countBy(fn (Booking $booking) => $booking->status->value);

        $items = collect(BookingStatus::cases())
            ->map(fn (BookingStatus $status) => [
                'label' => $labels[$status->value],
                'value' => (int) ($counts[$status->value] ?? 0),
            ])
            ->filter(fn (array $item) => $item['value'] > 0)
            ->values();

        return ['key' => 'booking_status', ...$this->donutChart('Distribusi status peminjaman', "{$months} bulan terakhir", $items)];
    }

    /**
     * @return array<string, mixed>
     */
    private function approvalDecisionChart(int $months): array
    {
        $labels = ['pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'];
        $counts = Approval::whereBetween('created_at', [$this->dashboardPeriodStart($months), now()])
            ->select('status')
            ->get()
            ->countBy(fn (Approval $approval) => $approval->status);

        $items = collect($labels)
            ->map(fn (string $label, string $status) => [
                'label' => $label,
                'value' => (int) ($counts[$status] ?? 0),
            ])
            ->filter(fn (array $item) => $item['value'] > 0)
            ->values();

        return ['key' => 'approval_decisions', ...$this->donutChart('Hasil keputusan persetujuan', "{$months} bulan terakhir", $items)];
    }

    /**
     * @return array<string, mixed>
     */
    private function pendingApprovalsByRoomChart(int $months): array
    {
        $approvals = Approval::actionable()
            ->where('approvals.created_at', '>=', $this->dashboardPeriodStart($months))
            ->with('booking.resource')
            ->get();
        $counts = $approvals->countBy(fn (Approval $approval) => (int) ($approval->booking->resource_id ?? 0));
        $names = $approvals->mapWithKeys(fn (Approval $approval) => [
            (int) ($approval->booking->resource_id ?? 0) => $approval->booking->resource->name ?? 'Ruangan atau aset',
        ]);

        $items = $counts->map(fn (int $count, int $resourceId) => [
            'label' => $names[$resourceId] ?? 'Ruangan atau aset',
            'value' => $count,
        ])
            ->sortByDesc('value')
            ->take(5)
            ->values();

        return ['key' => 'approval_queue', ...$this->barChart('Antrian persetujuan per ruangan', "{$months} bulan terakhir · menunggu keputusan", $items)];
    }

    /**
     * @return array<string, mixed>
     */
    private function topRoomsChart(int $months): array
    {
        $items = Booking::with('resource')
            ->where('status', BookingStatus::COMPLETED->value)
            ->whereBetween('starts_at', [$this->dashboardPeriodStart($months), now()])
            ->whereHas('resource', fn ($query) => $query->where('type', 'room'))
            ->get()
            ->groupBy('resource_id')
            ->map(fn (Collection $bookings) => [
                'label' => $bookings->first()->resource?->name ?? 'Ruangan',
                'value' => $bookings->count(),
            ])
            ->sortByDesc('value')
            ->take(5)
            ->values();

        return ['key' => 'top_rooms', ...$this->barChart('Ruangan paling sering dipakai', "{$months} bulan terakhir · peminjaman selesai", $items)];
    }

    /**
     * @return array<string, mixed>
     */
    private function roomUtilizationChart(int $months): array
    {
        $items = Booking::with('resource')
            ->where('status', BookingStatus::COMPLETED->value)
            ->whereBetween('starts_at', [$this->dashboardPeriodStart($months), now()])
            ->whereHas('resource', fn ($query) => $query->where('type', 'room'))
            ->get()
            ->groupBy('resource_id')
            ->map(fn (Collection $bookings) => [
                'label' => $bookings->first()->resource?->name ?? 'Ruangan',
                'value' => (int) $bookings->sum(fn (Booking $booking) => $booking->starts_at->diffInMinutes($booking->ends_at)),
            ])
            ->sortByDesc('value')
            ->take(5)
            ->values();

        return ['key' => 'room_usage', ...$this->barChart('Pemakaian ruangan', "{$months} bulan terakhir · total menit", $items)];
    }

    /**
     * @return array<string, mixed>
     */
    private function popularAssetsChart(int $months): array
    {
        $items = AssetLoan::with('asset.resource')
            ->whereBetween('created_at', [$this->dashboardPeriodStart($months), now()])
            ->get()
            ->groupBy('asset_id')
            ->map(fn (Collection $loans) => [
                'label' => $loans->first()->asset?->resource?->name ?? 'Aset',
                'value' => $loans->count(),
            ])
            ->sortByDesc('value')
            ->take(5)
            ->values();

        return ['key' => 'popular_assets', ...$this->barChart('Aset paling sering dipinjam', "{$months} bulan terakhir", $items)];
    }

    /**
     * @return array<string, mixed>
     */
    private function assetConditionChart(): array
    {
        $labels = [
            'good' => 'Baik',
            'damaged' => 'Rusak',
            'lost' => 'Hilang',
            'maintenance' => 'Perawatan',
        ];
        $counts = Asset::select('condition')->get()->countBy('condition');

        $items = collect($labels)
            ->map(fn (string $label, string $condition) => [
                'label' => $label,
                'value' => (int) ($counts[$condition] ?? 0),
            ])
            ->filter(fn (array $item) => $item['value'] > 0)
            ->values();

        return $this->donutChart('Kondisi aset', 'Saat ini', $items);
    }

    /**
     * First instant of the oldest month shown, in WIB.
     */
    private function dashboardMonthStart(int $months): Carbon
    {
        // Snap to the first of the month before subtracting, so the 31st cannot overflow.
        return now()->wib()->startOfMonth()->subMonths($months - 1);
    }

    private function dashboardPeriodStart(int $months): Carbon
    {
        return now()->subMonthsNoOverflow($months);
    }

    /**
     * @return Collection<int, Carbon>
     */
    private function dashboardMonths(int $months): Collection
    {
        return collect(CarbonPeriod::create($this->dashboardMonthStart($months), '1 month', now()->wib()->startOfMonth()));
    }

    /**
     * @param  Collection<int, array{label: string, value: int}>  $items
     * @return array<string, mixed>
     */
    private function barChart(string $title, string $note, Collection $items): array
    {
        $maximum = (int) $items->max('value');

        return [
            'title' => $title,
            'note' => $note,
            'kind' => 'bars',
            'items' => $items
                ->map(fn (array $item) => [
                    ...$item,
                    'percent' => $maximum > 0 ? (int) round($item['value'] / $maximum * 100) : 0,
                ])
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, array{label: string, value: int}>  $items
     * @return array<string, mixed>
     */
    private function donutChart(string $title, string $note, Collection $items): array
    {
        $total = (int) $items->sum('value');
        $palette = ['var(--forest)', 'var(--amber)', 'var(--danger)', 'var(--muted-2)', '#7ba894', '#b9cbbf'];
        $offset = 0;
        $segments = [];

        $legend = $items->map(function (array $item, int $index) use ($total, $palette, &$offset, &$segments): array {
            $percent = $total > 0 ? round($item['value'] / $total * 100, 2) : 0;
            $color = $palette[$index % count($palette)];
            $segments[] = "{$color} {$offset}% ".($offset + $percent).'%';
            $offset += $percent;

            return [...$item, 'percent' => (int) round($percent), 'color' => $color];
        })->all();

        if ($total > 0 && $offset < 100) {
            $segments[] = "var(--line) {$offset}% 100%";
        }

        return [
            'title' => $title,
            'note' => $note,
            'kind' => 'donut',
            'total' => $total,
            'gradient' => $total > 0 ? 'conic-gradient('.implode(', ', $segments).')' : 'conic-gradient(var(--line) 0 100%)',
            'items' => $legend,
        ];
    }

    public function approvals(Request $request)
    {
        $this->ensurePermission(Permission::APPROVALS_VIEW);

        $canDecide = $request->user()->hasPermission(Permission::APPROVALS_UPDATE);

        $approvals = Approval::actionable()
            ->with(['booking.user', 'booking.resource'])
            ->latest()
            ->paginate(20);

        return view('admin.approvals', compact('approvals', 'canDecide'));
    }

    public function approve(Request $request, Approval $approval, ApprovalService $approvalService)
    {
        $this->ensurePermission(Permission::APPROVALS_UPDATE);

        try {
            $approvalService->approve($approval, $request->user(), $request->input('notes'));
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return back()->with('success', 'Permintaan disetujui.');
    }

    public function reject(Request $request, Approval $approval, ApprovalService $approvalService)
    {
        $this->ensurePermission(Permission::APPROVALS_UPDATE);

        try {
            $approvalService->reject($approval, $request->user(), $request->input('notes'));
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return back()->with('success', 'Permintaan ditolak.');
    }

    public function resources()
    {
        $this->ensurePermission(Permission::RESOURCES_VIEW);

        $resources = Resource::with(['location', 'room', 'asset', 'category'])->latest()->paginate(20);
        $locations = Location::all();
        $roomCategories = Category::where('type', 'room')->orderBy('name')->get();
        $assetCategories = Category::where('type', 'asset')->orderBy('name')->get();
        $facilities = Facility::orderBy('name')->get();

        return view('admin.resources', compact('resources', 'locations', 'roomCategories', 'assetCategories', 'facilities'));
    }

    public function storeResource(Request $request)
    {
        $this->ensurePermission(Permission::RESOURCES_CREATE);

        $validated = $request->validate($this->resourceValidationRules($request));

        DB::transaction(function () use ($validated) {
            $resource = Resource::create([
                'name' => $validated['name'],
                'type' => $validated['type'],
                'location_id' => $validated['location_id'],
                'category_id' => $validated['category_id'] ?? null,
                'description' => $validated['description'] ?? null,
                'is_active' => true,
            ]);

            if ($resource->type->value === 'room') {
                $resource->room()->create([
                    'capacity' => $validated['capacity'],
                    'facilities' => $validated['facilities'] ?? null,
                    'open_time' => $validated['open_time'] ?? null,
                    'close_time' => $validated['close_time'] ?? null,
                ]);
            }

            if ($resource->type->value === 'asset') {
                $resource->asset()->create([
                    'code' => $validated['code'],
                    'condition' => $validated['condition'] ?? 'good',
                ]);
            }

            $resource->rules()->create([
                'buffer_minutes' => $validated['buffer_minutes'] ?? 0,
                'min_duration_minutes' => $validated['min_duration_minutes'] ?? 30,
                'max_duration_minutes' => $validated['max_duration_minutes'] ?? 480,
                'advance_booking_days' => $validated['advance_booking_days'] ?? 30,
                'approval_path' => $validated['approval_path'] ?? 'none',
                'cancellation_minutes_before' => $validated['cancellation_minutes_before'] ?? 60,
            ]);
        });

        return back()->with('success', 'Ruangan atau aset ditambahkan.');
    }

    public function editResource(Resource $resource)
    {
        $this->ensurePermission(Permission::RESOURCES_UPDATE);

        $locations = Location::orderBy('name')->get();
        $roomCategories = Category::where('type', 'room')->orderBy('name')->get();
        $assetCategories = Category::where('type', 'asset')->orderBy('name')->get();
        $facilities = Facility::orderBy('name')->get();

        return view('admin.resource-edit', compact('resource', 'locations', 'roomCategories', 'assetCategories', 'facilities'));
    }

    public function updateResource(Request $request, Resource $resource)
    {
        $this->ensurePermission(Permission::RESOURCES_UPDATE);

        $validated = $request->validate($this->resourceValidationRules($request, $resource));

        DB::transaction(function () use ($validated, $resource) {
            $resource->update([
                'name' => $validated['name'],
                'location_id' => $validated['location_id'],
                'category_id' => $validated['category_id'] ?? null,
                'description' => $validated['description'] ?? null,
            ]);

            if ($resource->type->value === 'room') {
                $resource->room()->updateOrCreate([], [
                    'capacity' => $validated['capacity'],
                    'facilities' => $validated['facilities'] ?? null,
                    'open_time' => $validated['open_time'] ?? null,
                    'close_time' => $validated['close_time'] ?? null,
                ]);
            }

            if ($resource->type->value === 'asset') {
                $resource->asset()->updateOrCreate([], [
                    'code' => $validated['code'],
                    'condition' => $validated['condition'] ?? 'good',
                ]);
            }

            $resource->rules()->updateOrCreate([], [
                'buffer_minutes' => $validated['buffer_minutes'] ?? 0,
                'min_duration_minutes' => $validated['min_duration_minutes'] ?? 30,
                'max_duration_minutes' => $validated['max_duration_minutes'] ?? 480,
                'advance_booking_days' => $validated['advance_booking_days'] ?? 30,
                'approval_path' => $validated['approval_path'] ?? 'none',
                'cancellation_minutes_before' => $validated['cancellation_minutes_before'] ?? 60,
            ]);
        });

        return redirect()->route('admin.resources')->with('success', 'Ruangan atau aset diperbarui.');
    }

    public function toggleResource(Resource $resource)
    {
        $this->ensurePermission(Permission::RESOURCES_UPDATE);

        $resource->update(['is_active' => ! $resource->is_active]);

        return back()->with('success', $resource->is_active ? 'Ruangan atau aset diaktifkan.' : 'Ruangan atau aset dinonaktifkan.');
    }

    public function destroyResource(Resource $resource)
    {
        $this->ensurePermission(Permission::RESOURCES_DELETE);

        if ($resource->hasActiveBookings()) {
            return back()->withErrors(['resource' => 'Ruangan atau aset masih memiliki pemesanan aktif. Selesaikan atau batalkan pemesanan terlebih dahulu.']);
        }

        $resource->delete();

        return back()->with('success', 'Ruangan atau aset dihapus.');
    }

    public function audit(Request $request)
    {
        $this->ensurePermission(Permission::AUDIT_VIEW);

        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $query = AuditLog::with('user')->latest();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                    ->orWhere('auditable_type', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }

        if ($request->filled('type')) {
            $query->where('auditable_type', $request->input('type'));
        }

        $timezone = config('app.display_timezone');

        if ($request->filled('from')) {
            $query->where('created_at', '>=', Carbon::parse($request->input('from'), $timezone)->startOfDay()->utc());
        }

        if ($request->filled('to')) {
            $query->where('created_at', '<=', Carbon::parse($request->input('to'), $timezone)->endOfDay()->utc());
        }

        $logs = $query->paginate(30)->withQueryString();
        $actions = AuditLog::query()->distinct()->orderBy('action')->pluck('action');
        $types = AuditLog::query()->distinct()->pluck('auditable_type')
            ->filter()
            ->mapWithKeys(fn (string $type) => [$type => match (class_basename($type)) {
                'Booking' => 'Peminjaman',
                'AssetLoan' => 'Peminjaman aset',
                'Resource' => 'Ruangan atau aset',
                'User' => 'Pengguna',
                'Location' => 'Lokasi',
                'Category' => 'Kategori',
                'Facility' => 'Fasilitas',
                'Strike' => 'Pelanggaran',
                'Suspension' => 'Pembatasan akun',
                'Approval' => 'Persetujuan',
                'Role' => 'Peran',
                default => 'Data lain',
            }]);

        return view('admin.audit', compact('logs', 'actions', 'types'));
    }

    private function resourceValidationRules(Request $request, ?Resource $resource = null): array
    {
        $type = $request->input('type', $resource?->type?->value);

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in($resource ? [$resource->type->value] : ['room', 'asset'])],
            'location_id' => ['required', 'exists:locations,id'],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('type', $type)],
            'description' => ['nullable', 'string'],
            'buffer_minutes' => ['nullable', 'integer', 'min:0'],
            'min_duration_minutes' => ['nullable', 'integer', 'min:1'],
            'max_duration_minutes' => ['nullable', 'integer', 'gte:min_duration_minutes'],
            'advance_booking_days' => ['nullable', 'integer', 'min:1'],
            'approval_path' => ['nullable', 'in:none,one_level,two_level'],
            'cancellation_minutes_before' => ['nullable', 'integer', 'min:0'],
        ];

        if ($type === 'room') {
            $rules['capacity'] = ['required', 'integer', 'min:1'];
            $rules['facilities'] = ['nullable', 'array'];
            $rules['facilities.*'] = ['string', Rule::exists('facilities', 'name')];
            $rules['open_time'] = ['nullable', 'date_format:H:i'];
            $rules['close_time'] = ['nullable', 'date_format:H:i', 'after:open_time'];
        }

        if ($type === 'asset') {
            $rules['code'] = [
                'required',
                'string',
                'max:255',
                Rule::unique('assets', 'code')->ignore($resource?->asset?->id),
            ];
            $rules['condition'] = ['nullable', 'in:good,damaged,lost,maintenance'];
        }

        return $rules;
    }
}
