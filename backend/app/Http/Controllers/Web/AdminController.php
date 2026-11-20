<?php

namespace App\Http\Controllers\Web;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Approval;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Category;
use App\Models\Facility;
use App\Models\Location;
use App\Models\Resource;
use App\Models\Strike;
use App\Services\ApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
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

    public function dashboard()
    {
        return view('admin.dashboard', [
            'totalBookings' => Booking::count(),
            'pendingApprovals' => Approval::where('status', 'pending')->count(),
            'activeResources' => Resource::where('is_active', true)->count(),
            'totalStrikes' => Strike::count(),
            'recentApprovals' => Approval::where('status', 'pending')
                ->with(['booking.user', 'booking.resource'])
                ->latest()
                ->limit(4)
                ->get(),
        ]);
    }

    public function approvals(Request $request)
    {
        $canDecide = $this->canDecideApprovals($request);

        $approvals = Approval::actionable()
            ->with(['booking.user', 'booking.resource'])
            ->latest()
            ->paginate(20);

        return view('admin.approvals', compact('approvals', 'canDecide'));
    }

    public function approve(Request $request, Approval $approval, ApprovalService $approvalService)
    {
        $this->ensureCanDecideApprovals($request);

        try {
            $approvalService->approve($approval, $request->user(), $request->input('notes'));
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return back()->with('success', 'Permintaan disetujui.');
    }

    public function reject(Request $request, Approval $approval, ApprovalService $approvalService)
    {
        $this->ensureCanDecideApprovals($request);

        try {
            $approvalService->reject($approval, $request->user(), $request->input('notes'));
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return back()->with('success', 'Permintaan ditolak.');
    }

    private function canDecideApprovals(Request $request): bool
    {
        return $request->user()->isAdmin()
            || $request->user()->hasRole(Role::APPROVER->value);
    }

    private function ensureCanDecideApprovals(Request $request): void
    {
        abort_unless($this->canDecideApprovals($request), 403, 'Hanya penyetuju yang bisa memutuskan permintaan.');
    }

    public function resources()
    {
        $this->ensureCanManageResources();

        $resources = Resource::with(['location', 'room', 'asset', 'category'])->latest()->paginate(20);
        $locations = Location::all();
        $roomCategories = Category::where('type', 'room')->orderBy('name')->get();
        $assetCategories = Category::where('type', 'asset')->orderBy('name')->get();
        $facilities = Facility::orderBy('name')->get();

        return view('admin.resources', compact('resources', 'locations', 'roomCategories', 'assetCategories', 'facilities'));
    }

    public function storeResource(Request $request)
    {
        $this->ensureCanManageResources();

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
        $this->ensureCanManageResources();

        $locations = Location::orderBy('name')->get();
        $roomCategories = Category::where('type', 'room')->orderBy('name')->get();
        $assetCategories = Category::where('type', 'asset')->orderBy('name')->get();
        $facilities = Facility::orderBy('name')->get();

        return view('admin.resource-edit', compact('resource', 'locations', 'roomCategories', 'assetCategories', 'facilities'));
    }

    public function updateResource(Request $request, Resource $resource)
    {
        $this->ensureCanManageResources();

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
        $this->ensureCanManageResources();

        $resource->update(['is_active' => ! $resource->is_active]);

        return back()->with('success', $resource->is_active ? 'Ruangan atau aset diaktifkan.' : 'Ruangan atau aset dinonaktifkan.');
    }

    public function destroyResource(Resource $resource)
    {
        $this->ensureAdmin();

        $hasActiveBookings = $resource->bookings()
            ->whereIn('status', ['pending', 'approved', 'checked_in'])
            ->where('ends_at', '>=', now())
            ->exists();

        if ($hasActiveBookings) {
            return back()->withErrors(['resource' => 'Ruangan atau aset masih memiliki pemesanan aktif. Selesaikan atau batalkan pemesanan terlebih dahulu.']);
        }

        $resource->delete();

        return back()->with('success', 'Ruangan atau aset dihapus.');
    }

    public function audit(Request $request)
    {
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

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->input('to'));
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

    private function ensureCanManageResources(): void
    {
        abort_unless(
            auth()->user()->isAdmin() || auth()->user()->hasRole(Role::RESOURCE_MANAGER->value),
            403,
            'Hanya pengelola ruangan dan aset yang bisa mengubah data ini.',
        );
    }

    private function ensureAdmin(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Hanya admin yang bisa melakukan aksi ini.');
    }
}
