<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\Role as RoleEnum;
use App\Jobs\ExpirePendingApprovals;
use App\Jobs\ExpireWaitlistOffers;
use App\Models\Booking;
use App\Models\Facility;
use App\Models\Resource;
use App\Models\Role;
use App\Models\User;
use App\Models\WaitlistEntry;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class AdminDataAccuracyTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['top_rooms', 'room'])]
    #[TestWith(['room_usage', 'room'])]
    #[TestWith(['popular_assets', 'asset'])]
    public function test_dashboard_chart_uses_the_selected_period(string $chart, string $type): void
    {
        $this->travelTo(Carbon::parse('2026-12-15 05:00:00', 'UTC'));
        $booking = $this->booking($this->resource($type), now()->subMonths(3), 'completed');
        if ($type === 'asset') {
            $asset = $booking->resource->asset()->create(['code' => 'CAM-001']);
            $this->travelTo(now()->subMonths(3));
            $asset->loans()->create(['booking_id' => $booking->id, 'user_id' => $booking->user_id]);
            $this->travelBack();
            $this->travelTo(Carbon::parse('2026-12-15 05:00:00', 'UTC'));
        }
        // The administrator sees "top_rooms"; the other two charts belong to a resource manager.
        $viewer = $this->userWithRole($chart === 'top_rooms' ? RoleEnum::ADMIN : RoleEnum::RESOURCE_MANAGER);

        $this->assertContains($booking->resource->name, $this->chartLabels($viewer, $chart, 6));
        $this->assertNotContains($booking->resource->name, $this->chartLabels($viewer, $chart, 2));
    }

    #[TestWith([2])]
    #[TestWith([4])]
    #[TestWith([6])]
    #[TestWith([12])]
    public function test_monthly_trend_has_one_bar_per_month_on_the_31st(int $months): void
    {
        $this->travelTo(Carbon::parse('2026-12-31 05:00:00', 'UTC'));

        $this->assertCount($months, $this->chartLabels($this->userWithRole(RoleEnum::ADMIN), 'booking_trend', $months));
    }

    public function test_monthly_trend_groups_bookings_by_wib_month(): void
    {
        $this->travelTo(Carbon::parse('2026-12-15 05:00:00', 'UTC'));
        // 01:00 WIB on 1 December
        $this->booking($this->resource(), Carbon::parse('2026-11-30 18:00:00', 'UTC'));

        $charts = $this->actingAs($this->userWithRole(RoleEnum::ADMIN))
            ->get(route('admin.dashboard', ['periods' => ['booking_trend' => 2]]))
            ->assertOk()
            ->viewData('charts');

        $this->assertSame([0, 1], array_column(collect($charts)->firstWhere('key', 'booking_trend')['items'], 'value'));
    }

    public function test_renaming_a_facility_renames_it_on_rooms(): void
    {
        $facility = Facility::firstOrCreate(['name' => 'Proyektor QA']);
        $room = $this->resource();
        $room->room()->create(['capacity' => 5, 'facilities' => ['Papan tulis', 'Proyektor QA']]);
        $other = $this->resource();
        $other->room()->create(['capacity' => 5, 'facilities' => ['Papan tulis']]);

        $this->actingAs($this->userWithRole(RoleEnum::ADMIN))
            ->put(route('admin.facilities.update', $facility), ['name' => 'Proyektor HD QA'])
            ->assertSessionHasNoErrors();

        $this->assertSame(['Papan tulis', 'Proyektor HD QA'], $room->room->fresh()->facilities);
        $this->assertSame(['Papan tulis'], $other->room->fresh()->facilities);
    }

    public function test_dashboard_ignores_approvals_of_released_bookings(): void
    {
        $booking = $this->booking($this->resource(), now()->addDay(), 'cancelled');
        $booking->approvals()->create(['level' => 1, 'status' => 'pending']);

        $response = $this->actingAs($this->userWithRole(RoleEnum::ADMIN))->get(route('admin.dashboard'))->assertOk();

        $this->assertSame(0, $response->viewData('pendingApprovals'));
        $this->assertCount(0, $response->viewData('recentApprovals'));
    }

    public function test_expiry_job_does_not_overwrite_a_cancelled_booking(): void
    {
        $booking = $this->booking($this->resource(), now()->addDay(), 'cancelled');
        $approval = $booking->approvals()->create(['level' => 1, 'status' => 'pending', 'expires_at' => now()->subHour()]);

        (new ExpirePendingApprovals)->handle();

        $this->assertSame(BookingStatus::CANCELLED, $booking->fresh()->status);
        $this->assertSame('pending', $approval->fresh()->status);
    }

    public function test_an_expired_waitlist_offer_moves_to_the_next_in_line(): void
    {
        $resource = $this->resource();
        $expired = $this->waitlistEntry($resource, 'offered', now()->subMinute());
        $next = $this->waitlistEntry($resource, 'waiting', null);

        app()->call([new ExpireWaitlistOffers, 'handle']);

        $this->assertSame('expired', $expired->fresh()->status);
        $this->assertSame('offered', $next->fresh()->status);
    }

    public function test_a_running_waitlist_offer_is_left_alone(): void
    {
        $resource = $this->resource();
        $running = $this->waitlistEntry($resource, 'offered', now()->addMinute());
        $next = $this->waitlistEntry($resource, 'waiting', null);

        app()->call([new ExpireWaitlistOffers, 'handle']);

        $this->assertSame('offered', $running->fresh()->status);
        $this->assertSame('waiting', $next->fresh()->status);
    }

    public function test_waitlist_expiry_is_scheduled(): void
    {
        $jobs = collect(app(Schedule::class)->events())->map->description;

        $this->assertContains(ExpireWaitlistOffers::class, $jobs);
    }

    /**
     * @return list<string>
     */
    private function chartLabels(User $viewer, string $key, int $months): array
    {
        $charts = $this->actingAs($viewer)
            ->get(route('admin.dashboard', ['periods' => [$key => $months]]))
            ->assertOk()
            ->viewData('charts');

        return array_column(collect($charts)->firstWhere('key', $key)['items'], 'label');
    }

    private function resource(string $type = 'room'): Resource
    {
        return Resource::create(['name' => fake()->unique()->words(2, true), 'type' => $type, 'is_active' => true]);
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

    private function waitlistEntry(Resource $resource, string $status, ?Carbon $offerExpiresAt): WaitlistEntry
    {
        return WaitlistEntry::create([
            'user_id' => User::factory()->create()->id,
            'resource_id' => $resource->id,
            'desired_start' => now()->addDay()->startOfDay()->addHours(3),
            'desired_end' => now()->addDay()->startOfDay()->addHours(4),
            'status' => $status,
            'offer_expires_at' => $offerExpiresAt,
        ]);
    }

    private function userWithRole(RoleEnum $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => $role]));

        return $user;
    }
}
