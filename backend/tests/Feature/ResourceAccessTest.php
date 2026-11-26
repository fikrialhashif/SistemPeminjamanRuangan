<?php

namespace Tests\Feature;

use App\Enums\Role as RoleEnum;
use App\Models\Booking;
use App\Models\Department;
use App\Models\Resource;
use App\Models\ResourceAccess;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ResourceAccessTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_role_restricted_resource_is_hidden_from_other_members(bool $hasDepartment): void
    {
        $this->roleRestricted();
        $member = User::factory()->create([
            'department_id' => $hasDepartment ? Department::create(['name' => 'Keuangan', 'code' => 'KEU'])->id : null,
        ]);

        $this->actingAs($member)
            ->getJson('/api/v1/resources')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_role_and_department_matches_see_their_resources(): void
    {
        $forRole = $this->roleRestricted();
        $department = Department::create(['name' => 'Keuangan', 'code' => 'KEU']);
        $forDepartment = $this->resource();
        ResourceAccess::create(['resource_id' => $forDepartment->id, 'department_id' => $department->id]);
        $user = $this->userWithRole(RoleEnum::APPROVER, ['department_id' => $department->id]);

        $ids = $this->actingAs($user)->getJson('/api/v1/resources')->assertOk()->json('data.*.id');

        $this->assertEqualsCanonicalizing([$forRole->id, $forDepartment->id], $ids);
    }

    #[TestWith([''])]
    #[TestWith(['/availability?date=2026-12-02'])]
    public function test_restricted_resource_detail_returns_403_to_other_members(string $suffix): void
    {
        $resource = $this->roleRestricted();

        $this->actingAs(User::factory()->create())
            ->getJson("/api/v1/resources/{$resource->id}{$suffix}")
            ->assertForbidden();
    }

    #[TestWith([RoleEnum::APPROVER])]
    #[TestWith([RoleEnum::ADMIN])]
    public function test_allowed_users_can_open_a_restricted_resource(RoleEnum $role): void
    {
        $resource = $this->roleRestricted();

        $this->actingAs($this->userWithRole($role))
            ->getJson("/api/v1/resources/{$resource->id}")
            ->assertOk()
            ->assertJsonPath('id', $resource->id);
    }

    public function test_api_returns_422_when_deleting_a_resource_with_active_bookings(): void
    {
        $resource = $this->resource();
        $this->booking($resource, now()->addDay());

        $this->actingAs($this->userWithRole(RoleEnum::ADMIN))
            ->deleteJson("/api/v1/resources/{$resource->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('resource');

        $this->assertNotSoftDeleted($resource);
    }

    public function test_api_deletes_a_resource_that_only_has_past_bookings(): void
    {
        $resource = $this->resource();
        $this->booking($resource, now()->subDays(3));

        $this->actingAs($this->userWithRole(RoleEnum::ADMIN))
            ->deleteJson("/api/v1/resources/{$resource->id}")
            ->assertOk();

        $this->assertSoftDeleted($resource);
    }

    public function test_booking_of_an_already_deleted_resource_can_still_be_cancelled(): void
    {
        $resource = $this->resource();
        $booking = $this->booking($resource, now()->addDay());
        $resource->delete();

        $this->actingAs($booking->user)->deleteJson("/api/v1/bookings/{$booking->id}")->assertOk();
    }

    // [report endpoint, JSON path that counts the row]
    #[TestWith(['no-show-rate', 'no_shows'])]
    #[TestWith(['verification-success', 'total_attempts'])]
    public function test_report_range_includes_the_whole_last_wib_day(string $report, string $path): void
    {
        // 30 November 23:00 WIB
        $this->travelTo(Carbon::parse('2026-11-30 16:00:00', 'UTC'));
        $booking = $this->booking($this->resource(), now(), 'no_show');
        $booking->checkInAttempts()->create(['user_id' => $booking->user_id, 'result' => 'rejected']);

        $this->actingAs($this->userWithRole(RoleEnum::ADMIN))
            ->getJson("/api/v1/reports/{$report}?from=2026-11-30&to=2026-11-30")
            ->assertOk()
            ->assertJsonPath($path, 1);
    }

    public function test_popular_assets_report_includes_the_whole_last_wib_day(): void
    {
        $this->travelTo(Carbon::parse('2026-11-30 16:00:00', 'UTC'));
        $resource = Resource::create(['name' => 'Kamera', 'type' => 'asset']);
        $asset = $resource->asset()->create(['code' => 'CAM-001']);
        $booking = $this->booking($resource, now());
        $asset->loans()->create(['booking_id' => $booking->id, 'user_id' => $booking->user_id]);

        $this->actingAs($this->userWithRole(RoleEnum::ADMIN))
            ->getJson('/api/v1/reports/popular-assets?from=2026-11-30&to=2026-11-30')
            ->assertOk()
            ->assertJsonCount(1);
    }

    private function resource(): Resource
    {
        return Resource::create(['name' => 'Ruang Rapat', 'type' => 'room', 'is_active' => true]);
    }

    private function roleRestricted(): Resource
    {
        $resource = $this->resource();
        $role = Role::firstOrCreate(['name' => RoleEnum::APPROVER]);
        ResourceAccess::create(['resource_id' => $resource->id, 'role_id' => $role->id]);

        return $resource;
    }

    private function booking(Resource $resource, Carbon $startsAt, string $status = 'approved'): Booking
    {
        return Booking::create([
            'user_id' => User::factory()->create()->id,
            'resource_id' => $resource->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHour(),
            'status' => $status,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function userWithRole(RoleEnum $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->roles()->attach(Role::firstOrCreate(['name' => $role]));

        return $user;
    }
}
