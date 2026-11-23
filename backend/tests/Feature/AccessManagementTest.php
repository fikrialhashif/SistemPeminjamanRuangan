<?php

namespace Tests\Feature;

use App\Enums\Role as RoleEnum;
use App\Models\Approval;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Location;
use App\Models\Resource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_access_management_page(): void
    {
        Role::firstOrCreate(['name' => RoleEnum::APPROVER]);
        Role::firstOrCreate(['name' => RoleEnum::RESOURCE_MANAGER]);

        $this->actingAs($this->userWithRole(RoleEnum::ADMIN))
            ->get(route('admin.access'))
            ->assertOk()
            ->assertSee('Manajemen akses')
            ->assertSee('data-access-role', false)
            ->assertSee('data-access-panel', false)
            ->assertSee('data-access-select-all', false)
            ->assertSee('Penyetuju')
            ->assertSee('Pengelola ruangan dan aset')
            ->assertSee('Pengaturan')
            ->assertSee('value="approvals.update"', false)
            ->assertSee('value="resources.create"', false)
            ->assertSee('value="resources.delete"', false)
            ->assertSee('value="settings.view"', false)
            ->assertSee('value="settings.update"', false);
    }

    public function test_non_admin_cannot_view_access_management_page(): void
    {
        $this->actingAs($this->userWithRole(RoleEnum::RESOURCE_MANAGER))
            ->get(route('admin.access'))
            ->assertForbidden();
    }

    public function test_admin_can_update_role_permissions_and_the_change_is_audited(): void
    {
        $role = Role::firstOrCreate(['name' => RoleEnum::APPROVER]);

        $this->actingAs($this->userWithRole(RoleEnum::ADMIN))
            ->put(route('admin.access.update', $role), [
                'permissions' => ['approvals.view'],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(['approvals.view'], $role->fresh()->permissions);

        $this->assertTrue(
            AuditLog::where('auditable_type', Role::class)
                ->where('auditable_id', $role->id)
                ->where('action', 'updated')
                ->exists(),
        );
    }

    public function test_auto_save_returns_updated_permissions_as_json(): void
    {
        $role = Role::firstOrCreate(['name' => RoleEnum::APPROVER]);

        $this->actingAs($this->userWithRole(RoleEnum::ADMIN))
            ->putJson(route('admin.access.update', $role), [
                'permissions' => ['approvals.view', 'approvals.update'],
            ])
            ->assertOk()
            ->assertJsonPath('permissions', ['approvals.view', 'approvals.update']);

        $this->assertSame(['approvals.view', 'approvals.update'], $role->fresh()->permissions);
    }

    public function test_permissions_outside_the_role_catalog_are_rejected(): void
    {
        $role = Role::firstOrCreate(['name' => RoleEnum::RESOURCE_MANAGER]);

        $this->actingAs($this->userWithRole(RoleEnum::ADMIN))
            ->put(route('admin.access.update', $role), [
                'permissions' => ['access.update'],
            ])
            ->assertSessionHasErrors('permissions.0');

        $this->assertNull($role->fresh()->permissions);
    }

    public function test_admin_role_cannot_be_edited(): void
    {
        $role = Role::firstOrCreate(['name' => RoleEnum::ADMIN]);

        $this->actingAs($this->userWithRole(RoleEnum::ADMIN))
            ->put(route('admin.access.update', $role), [
                'permissions' => ['approvals.view'],
            ])
            ->assertForbidden();
    }

    public function test_non_admin_with_user_permissions_cannot_assign_admin_role(): void
    {
        $manager = $this->userWithRole(RoleEnum::RESOURCE_MANAGER, ['users.view', 'users.create', 'users.update']);
        $adminRole = Role::firstOrCreate(['name' => RoleEnum::ADMIN]);
        $memberRole = Role::firstOrCreate(['name' => RoleEnum::MEMBER]);
        $memberUser = $this->userWithRole(RoleEnum::MEMBER);
        $adminUser = $this->userWithRole(RoleEnum::ADMIN);

        $this->actingAs($manager)
            ->post(route('admin.users.store'), [
                'name' => 'Penyusup',
                'email' => 'penyusup@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role_id' => $adminRole->id,
            ])
            ->assertForbidden();

        $this->actingAs($manager)
            ->put(route('admin.users.update', $memberUser), [
                'name' => $memberUser->name,
                'email' => $memberUser->email,
                'role_id' => $adminRole->id,
            ])
            ->assertForbidden();

        $this->actingAs($manager)
            ->put(route('admin.users.update', $adminUser), [
                'name' => $adminUser->name,
                'email' => $adminUser->email,
                'role_id' => $memberRole->id,
            ])
            ->assertForbidden();
    }

    public function test_approver_without_update_permission_cannot_decide(): void
    {
        $approver = $this->userWithRole(RoleEnum::APPROVER, ['approvals.view']);

        $approval = Approval::create([
            'booking_id' => $this->booking()->id,
            'level' => 1,
            'status' => 'pending',
        ]);

        $this->actingAs($approver)
            ->get(route('admin.approvals'))
            ->assertOk()
            ->assertSee('tidak punya izin untuk memutuskan')
            ->assertDontSee('Setujui');

        $this->actingAs($approver)
            ->post(route('admin.approvals.approve', $approval))
            ->assertForbidden();
    }

    public function test_approver_with_update_permission_can_decide(): void
    {
        $approver = $this->userWithRole(RoleEnum::APPROVER, ['approvals.view', 'approvals.update']);

        $approval = Approval::create([
            'booking_id' => $this->booking()->id,
            'level' => 1,
            'status' => 'pending',
        ]);

        $this->actingAs($approver)
            ->get(route('admin.approvals'))
            ->assertOk()
            ->assertSee('Setujui');

        $this->actingAs($approver)
            ->post(route('admin.approvals.approve', $approval))
            ->assertSessionHasNoErrors();

        $this->assertSame('approved', $approval->fresh()->status);
    }

    public function test_resource_manager_needs_permission_to_create_resources(): void
    {
        $manager = $this->userWithRole(RoleEnum::RESOURCE_MANAGER, ['resources.view']);

        $location = Location::create([
            'name' => 'Gedung Uji',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius' => 100,
        ]);

        $payload = [
            'name' => 'Ruang Uji',
            'type' => 'room',
            'location_id' => $location->id,
            'capacity' => 10,
        ];

        $this->actingAs($manager)
            ->get(route('admin.resources'))
            ->assertOk();

        $this->actingAs($manager)
            ->post(route('admin.resources.store'), $payload)
            ->assertForbidden();

        $manager->roles->first()->update(['permissions' => ['resources.view', 'resources.create']]);

        $this->actingAs($manager)
            ->post(route('admin.resources.store'), $payload)
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('resources', ['name' => 'Ruang Uji']);
    }

    public function test_view_permission_unlocks_page_without_update(): void
    {
        $manager = $this->userWithRole(RoleEnum::RESOURCE_MANAGER, ['settings.view']);

        $this->actingAs($manager)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.settings'), false);

        $this->actingAs($manager)
            ->get(route('admin.settings'))
            ->assertOk()
            ->assertSee('Pengaturan sistem')
            ->assertSee('Anda hanya memiliki akses lihat untuk pengaturan.');

        $this->actingAs($manager)
            ->put(route('admin.settings.update'), ['settings' => []])
            ->assertForbidden();
    }

    public function test_revoked_view_permission_hides_navigation_and_blocks_page(): void
    {
        $manager = $this->userWithRole(RoleEnum::RESOURCE_MANAGER, ['bookings.view']);

        $this->actingAs($manager)
            ->get(route('admin.loans'))
            ->assertForbidden();

        $this->actingAs($manager)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee(route('admin.loans'), false);
    }

    private function userWithRole(RoleEnum $role, ?array $permissions = null): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => $role]);

        if ($permissions !== null) {
            $role->update(['permissions' => $permissions]);
        }

        $user->roles()->attach($role);

        return $user;
    }

    private function booking(): Booking
    {
        $user = User::factory()->create();

        $location = Location::create([
            'name' => 'Gedung Uji',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius' => 100,
        ]);

        $resource = Resource::create([
            'name' => 'Ruang Uji',
            'type' => 'room',
            'location_id' => $location->id,
        ]);

        return Booking::create([
            'user_id' => $user->id,
            'resource_id' => $resource->id,
            'title' => 'Rapat',
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHours(2),
            'status' => 'pending',
        ]);
    }
}
