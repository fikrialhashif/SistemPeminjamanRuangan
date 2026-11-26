<?php

namespace Tests\Feature;

use App\Enums\Role as RoleEnum;
use App\Models\AssetLoan;
use App\Models\Booking;
use App\Models\Resource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

/**
 * The API must honour the permissions an admin configures for a role, like the web panel does.
 */
class ApiPermissionTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['getJson', '/api/v1/approvals/pending'])]
    #[TestWith(['postJson', '/api/v1/approvals/{approval}/approve'])]
    #[TestWith(['postJson', '/api/v1/approvals/{approval}/reject'])]
    public function test_approver_without_permissions_is_forbidden(string $method, string $url): void
    {
        $approval = $this->loan()->booking->approvals()->create(['level' => 1, 'status' => 'pending']);

        $this->actingAs($this->userWithPermissions(RoleEnum::APPROVER, []))
            ->{$method}(str_replace('{approval}', (string) $approval->id, $url))
            ->assertForbidden();
    }

    public function test_approver_with_view_only_permission_cannot_decide(): void
    {
        $approval = $this->loan()->booking->approvals()->create(['level' => 1, 'status' => 'pending']);
        $approver = $this->userWithPermissions(RoleEnum::APPROVER, ['approvals.view']);

        $this->actingAs($approver)->getJson('/api/v1/approvals/pending')->assertOk();
        $this->actingAs($approver)->postJson("/api/v1/approvals/{$approval->id}/approve")->assertForbidden();
    }

    #[TestWith(['handover'])]
    #[TestWith(['return'])]
    #[TestWith(['override'])]
    #[TestWith(['damage'])]
    public function test_manager_without_loan_update_permission_is_forbidden(string $action): void
    {
        $loan = $this->loan();

        $this->actingAs($this->userWithPermissions(RoleEnum::RESOURCE_MANAGER, ['loans.view']))
            ->postJson("/api/v1/loans/{$loan->id}/{$action}", [
                'face_image' => 'face',
                'asset_token' => 'token',
                'gps_latitude' => 0,
                'gps_longitude' => 0,
                'gps_accuracy' => 1,
                'condition' => 'good',
                'reason' => 'Alasan yang cukup panjang',
                'notes' => 'Rusak',
            ])
            ->assertForbidden();
    }

    public function test_manager_without_loan_view_permission_sees_only_own_loans(): void
    {
        $this->loan();

        $this->actingAs($this->userWithPermissions(RoleEnum::RESOURCE_MANAGER, []))
            ->getJson('/api/v1/loans')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_manager_with_default_permissions_sees_all_loans(): void
    {
        $this->loan();

        $this->actingAs($this->userWithPermissions(RoleEnum::RESOURCE_MANAGER, null))
            ->getJson('/api/v1/loans')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_manager_without_resource_permissions_cannot_create_or_update(): void
    {
        $resource = $this->loan()->asset->resource;
        $manager = $this->userWithPermissions(RoleEnum::RESOURCE_MANAGER, ['resources.view']);

        $this->actingAs($manager)
            ->postJson('/api/v1/resources', ['name' => 'Ruang', 'type' => 'room', 'capacity' => 4])
            ->assertForbidden();
        $this->actingAs($manager)
            ->putJson("/api/v1/resources/{$resource->id}", ['name' => 'Baru'])
            ->assertForbidden();
    }

    /**
     * @param  list<string>|null  $permissions  null keeps the role defaults
     */
    private function userWithPermissions(RoleEnum $name, ?array $permissions): User
    {
        $role = Role::firstOrCreate(['name' => $name]);
        $role->update(['permissions' => $permissions]);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    private function loan(): AssetLoan
    {
        $resource = Resource::create(['name' => 'Kamera', 'type' => 'asset', 'is_active' => true]);
        $asset = $resource->asset()->create(['code' => 'CAM-001', 'condition' => 'good']);
        $borrower = User::factory()->create();
        $booking = Booking::create([
            'user_id' => $borrower->id,
            'resource_id' => $resource->id,
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHours(2),
            'status' => 'pending',
        ]);

        return AssetLoan::create([
            'booking_id' => $booking->id,
            'asset_id' => $asset->id,
            'user_id' => $borrower->id,
            'status' => 'reserved',
            'due_at' => $booking->ends_at,
        ]);
    }
}
