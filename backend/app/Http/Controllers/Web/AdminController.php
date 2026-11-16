<?php

namespace App\Http\Controllers\Web;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Approval;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Location;
use App\Models\Resource;
use App\Models\Strike;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

                return back()->withErrors(['email' => 'Unauthorized access.']);
            }

            $request->session()->regenerate();

            return redirect()->route('admin.dashboard');
        }

        return back()->withErrors(['email' => 'Invalid credentials.'])->onlyInput('email');
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
        ]);
    }

    public function approvals()
    {
        $approvals = Approval::where('status', 'pending')
            ->with(['booking.user', 'booking.resource'])
            ->latest()
            ->paginate(20);

        return view('admin.approvals', compact('approvals'));
    }

    public function approve(Request $request, Approval $approval)
    {
        $approval->update([
            'status' => 'approved',
            'approver_id' => Auth::id(),
            'notes' => $request->input('notes'),
            'decided_at' => now(),
        ]);

        return back()->with('success', 'Approval approved.');
    }

    public function reject(Request $request, Approval $approval)
    {
        $approval->update([
            'status' => 'rejected',
            'approver_id' => Auth::id(),
            'notes' => $request->input('notes'),
            'decided_at' => now(),
        ]);

        return back()->with('success', 'Approval rejected.');
    }

    public function resources()
    {
        $resources = Resource::with(['location', 'room', 'asset'])->latest()->paginate(20);
        $locations = Location::all();

        return view('admin.resources', compact('resources', 'locations'));
    }

    public function storeResource(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:room,asset'],
            'location_id' => ['required', 'exists:locations,id'],
            'category' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        Resource::create($validated);

        return back()->with('success', 'Resource created.');
    }

    public function audit()
    {
        $logs = AuditLog::with('user')->latest()->paginate(30);

        return view('admin.audit', compact('logs'));
    }
}
