<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\Role as RoleEnum;
use App\Models\Approval;
use App\Models\Booking;
use App\Models\Resource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

/**
 * A booking requested by an approver is decided by a resource manager on the web panel.
 * Nobody decides their own booking.
 */
class ApproverBookingApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_resource_manager_sees_and_approves_an_approvers_booking_on_the_web(): void
    {
        $approval = $this->approvalFor($this->userWithRoles(RoleEnum::APPROVER));
        $manager = $this->userWithRoles(RoleEnum::RESOURCE_MANAGER);

        $this->actingAs($manager)
            ->get(route('admin.approvals'))
            ->assertOk()
            ->assertSee(route('admin.approvals.approve', $approval));
        $this->actingAs($manager)
            ->post(route('admin.approvals.approve', $approval))
            ->assertSessionHasNoErrors();

        $this->assertSame(BookingStatus::APPROVED, $approval->booking->fresh()->status);
        $this->assertSame($manager->id, $approval->fresh()->approver_id);
    }

    public function test_resource_manager_can_reject_an_approvers_booking(): void
    {
        $approval = $this->approvalFor($this->userWithRoles(RoleEnum::APPROVER));

        $this->actingAs($this->userWithRoles(RoleEnum::RESOURCE_MANAGER))
            ->post(route('admin.approvals.reject', $approval))
            ->assertSessionHasNoErrors();

        $this->assertSame(BookingStatus::REJECTED, $approval->booking->fresh()->status);
    }

    public function test_resource_manager_decides_both_levels(): void
    {
        $first = $this->approvalFor($this->userWithRoles(RoleEnum::APPROVER));
        $second = $first->booking->approvals()->create(['level' => 2, 'status' => 'pending']);
        $manager = $this->userWithRoles(RoleEnum::RESOURCE_MANAGER);

        $this->actingAs($manager)->post(route('admin.approvals.approve', $first))->assertSessionHasNoErrors();
        $this->assertSame(BookingStatus::PENDING, $first->booking->fresh()->status);
        $this->actingAs($manager)->post(route('admin.approvals.approve', $second))->assertSessionHasNoErrors();

        $this->assertSame(BookingStatus::APPROVED, $first->booking->fresh()->status);
    }

    public function test_resource_manager_does_not_handle_member_bookings_by_default(): void
    {
        $approval = $this->approvalFor($this->userWithRoles(RoleEnum::MEMBER));
        $manager = $this->userWithRoles(RoleEnum::RESOURCE_MANAGER);

        $this->actingAs($manager)
            ->get(route('admin.approvals'))
            ->assertOk()
            ->assertDontSee(route('admin.approvals.approve', $approval));
        $this->actingAs($manager)->post(route('admin.approvals.approve', $approval))->assertForbidden();

        $this->assertSame('pending', $approval->fresh()->status);
    }

    public function test_resource_manager_with_approval_permission_also_handles_member_bookings(): void
    {
        $approval = $this->approvalFor($this->userWithRoles(RoleEnum::MEMBER));
        $manager = $this->userWithRoles(RoleEnum::RESOURCE_MANAGER);
        $manager->roles()->first()->update(['permissions' => ['approvals.view', 'approvals.update']]);

        $this->actingAs($manager)->post(route('admin.approvals.approve', $approval))->assertSessionHasNoErrors();

        $this->assertSame(BookingStatus::APPROVED, $approval->booking->fresh()->status);
    }

    #[TestWith(['admin.approvals.approve'])]
    #[TestWith(['admin.approvals.reject'])]
    public function test_another_approver_cannot_decide_an_approvers_booking_on_the_web(string $route): void
    {
        $approval = $this->approvalFor($this->userWithRoles(RoleEnum::APPROVER));
        $other = $this->userWithRoles(RoleEnum::APPROVER);

        $this->actingAs($other)
            ->get(route('admin.approvals'))
            ->assertOk()
            ->assertDontSee(route('admin.approvals.approve', $approval));
        $this->actingAs($other)->post(route($route, $approval))->assertForbidden();

        $this->assertSame('pending', $approval->fresh()->status);
    }

    public function test_approver_still_decides_member_bookings(): void
    {
        $approval = $this->approvalFor($this->userWithRoles(RoleEnum::MEMBER));

        $this->actingAs($this->userWithRoles(RoleEnum::APPROVER))
            ->post(route('admin.approvals.approve', $approval))
            ->assertSessionHasNoErrors();

        $this->assertSame(BookingStatus::APPROVED, $approval->booking->fresh()->status);
    }

    public function test_admin_can_decide_an_approvers_booking_on_the_web(): void
    {
        $approval = $this->approvalFor($this->userWithRoles(RoleEnum::APPROVER));

        $this->actingAs($this->userWithRoles(RoleEnum::ADMIN))
            ->post(route('admin.approvals.approve', $approval))
            ->assertSessionHasNoErrors();

        $this->assertSame(BookingStatus::APPROVED, $approval->booking->fresh()->status);
    }

    #[TestWith([[RoleEnum::ADMIN]])]
    #[TestWith([[RoleEnum::APPROVER, RoleEnum::RESOURCE_MANAGER]])]
    #[TestWith([[RoleEnum::APPROVER]])]
    public function test_nobody_decides_their_own_booking(array $roles): void
    {
        $requester = $this->userWithRoles(...$roles);
        $approval = $this->approvalFor($requester);

        $this->actingAs($requester)
            ->get(route('admin.approvals'))
            ->assertOk()
            ->assertDontSee(route('admin.approvals.approve', $approval));
        $this->actingAs($requester)->post(route('admin.approvals.approve', $approval))->assertForbidden();
        $this->actingAs($requester)->postJson("/api/v1/approvals/{$approval->id}/approve")->assertForbidden();

        $this->assertSame('pending', $approval->fresh()->status);
    }

    #[TestWith([RoleEnum::APPROVER])]
    #[TestWith([RoleEnum::ADMIN])]
    public function test_api_neither_lists_nor_decides_an_approvers_booking(RoleEnum $role): void
    {
        $approval = $this->approvalFor($this->userWithRoles(RoleEnum::APPROVER));
        $memberApproval = $this->approvalFor($this->userWithRoles(RoleEnum::MEMBER));
        $decider = $this->userWithRoles($role);

        $ids = $this->actingAs($decider)->getJson('/api/v1/approvals/pending')->assertOk()->json('data.*.id');
        $this->assertSame([$memberApproval->id], $ids);
        $this->actingAs($decider)->postJson("/api/v1/approvals/{$approval->id}/approve")->assertForbidden();
        $this->actingAs($decider)->postJson("/api/v1/approvals/{$approval->id}/reject")->assertForbidden();

        $this->assertSame('pending', $approval->fresh()->status);
    }

    public function test_booking_made_by_an_approver_waits_for_the_resource_manager(): void
    {
        $approver = $this->userWithRoles(RoleEnum::APPROVER);
        $resource = Resource::create(['name' => 'Kamera', 'type' => 'asset', 'is_active' => true]);

        $this->actingAs($approver)
            ->postJson('/api/v1/bookings', [
                'resource_id' => $resource->id,
                'starts_at' => now()->addDay()->toIso8601String(),
                'ends_at' => now()->addDay()->addHour()->toIso8601String(),
            ])
            ->assertCreated()
            ->assertJsonPath('status', 'pending');

        $this->actingAs($this->userWithRoles(RoleEnum::RESOURCE_MANAGER))
            ->post(route('admin.approvals.approve', Approval::sole()))
            ->assertSessionHasNoErrors();

        $this->assertSame(BookingStatus::APPROVED, Booking::sole()->status);
    }

    public function test_resource_manager_gets_the_approval_menu_and_only_their_queue_on_the_dashboard(): void
    {
        $this->approvalFor($this->userWithRoles(RoleEnum::APPROVER));
        $this->approvalFor($this->userWithRoles(RoleEnum::MEMBER));

        $response = $this->actingAs($this->userWithRoles(RoleEnum::RESOURCE_MANAGER))
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.approvals'));

        $this->assertSame(1, $response->viewData('pendingApprovals'));
        $this->assertCount(1, $response->viewData('recentApprovals'));
    }

    public function test_panel_user_without_any_approval_authority_is_refused(): void
    {
        $approver = $this->userWithRoles(RoleEnum::APPROVER);
        $approver->roles()->first()->update(['permissions' => []]);

        $this->actingAs($approver)->get(route('admin.approvals'))->assertForbidden();
    }

    private function approvalFor(User $requester): Approval
    {
        $resource = Resource::create(['name' => 'Ruang Rapat', 'type' => 'room', 'is_active' => true]);
        $booking = Booking::create([
            'user_id' => $requester->id,
            'resource_id' => $resource->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'status' => 'pending',
        ]);

        return $booking->approvals()->create(['level' => 1, 'status' => 'pending']);
    }

    private function userWithRoles(RoleEnum ...$roles): User
    {
        $user = User::factory()->create();

        foreach ($roles as $role) {
            $user->roles()->attach(Role::firstOrCreate(['name' => $role]));
        }

        return $user;
    }
}
