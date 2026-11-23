<?php

namespace App\Http\Controllers\Web;

use App\Enums\Permission;
use App\Enums\Role as RoleEnum;
use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccessManagementController extends Controller
{
    public function index(): View
    {
        $this->ensureAdmin();

        $order = array_flip([
            RoleEnum::APPROVER->value,
            RoleEnum::RESOURCE_MANAGER->value,
        ]);

        $roles = Role::all()
            ->filter(fn (Role $role): bool => $this->isConfigurable($role))
            ->sortBy(fn (Role $role): int => $order[$role->name->value] ?? PHP_INT_MAX)
            ->values();

        $roleRows = $roles->map(fn (Role $role): array => [
            'role' => $role,
            'label' => $this->roleLabel($role),
            'description' => $this->roleDescription($role),
            'catalog' => Permission::catalogForRole($role->name),
            'current' => $role->permissions ?? Permission::defaultsForRole($role->name),
        ]);

        return view('admin.access', [
            'roleRows' => $roleRows,
            'actionLabels' => Permission::actionLabels(),
        ]);
    }

    public function update(Request $request, Role $role): JsonResponse|RedirectResponse
    {
        $this->ensureAdmin();

        abort_unless($this->isConfigurable($role), 403, 'Akses peran ini tidak bisa diatur.');

        $validated = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(Permission::valuesForRole($role->name))],
        ]);

        $role->update([
            'permissions' => array_values($validated['permissions'] ?? []),
        ]);

        $message = 'Akses peran '.$this->roleLabel($role).' disimpan.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'permissions' => $role->fresh()->permissions ?? [],
            ]);
        }

        return back()->with('success', $message);
    }

    private function isConfigurable(Role $role): bool
    {
        return in_array($role->name, [RoleEnum::APPROVER, RoleEnum::RESOURCE_MANAGER], true);
    }

    private function roleLabel(Role $role): string
    {
        return match ($role->name) {
            RoleEnum::ADMIN => 'Admin',
            RoleEnum::APPROVER => 'Penyetuju',
            RoleEnum::RESOURCE_MANAGER => 'Pengelola ruangan dan aset',
            RoleEnum::MEMBER => 'Anggota',
        };
    }

    private function roleDescription(Role $role): string
    {
        return match ($role->name) {
            RoleEnum::ADMIN => 'Selalu memiliki semua akses panel admin.',
            RoleEnum::APPROVER => 'Memutuskan permintaan peminjaman.',
            RoleEnum::RESOURCE_MANAGER => 'Mengelola ruangan, aset, dan peminjaman.',
            RoleEnum::MEMBER => 'Tidak bisa masuk ke panel admin.',
        };
    }

    private function ensureAdmin(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Hanya admin yang bisa mengakses halaman ini.');
    }
}
