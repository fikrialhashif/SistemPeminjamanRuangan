<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\Role as RoleEnum;
use App\Jobs\ExpireNoShowBookings;
use App\Models\Booking;
use App\Models\BookingSeries;
use App\Models\Resource;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Suspension;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

/**
 * Asset resources keep these cases independent of room face enrollment.
 */
class BookingRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-12-01 01:00:00', 'UTC'));
    }

    #[TestWith([null])]
    #[TestWith([0])]
    #[TestWith([60])]
    public function test_member_cannot_cancel_a_booking_that_already_started(?int $deadline): void
    {
        $resource = $this->resource();
        if ($deadline !== null) {
            $resource->rules()->create(['cancellation_minutes_before' => $deadline]);
        }
        $booking = $this->booking($resource, now()->subMinute());

        $this->actingAs($booking->user)
            ->deleteJson("/api/v1/bookings/{$booking->id}")
            ->assertStatus(422);

        $this->assertSame(BookingStatus::APPROVED, $booking->fresh()->status);
    }

    public function test_admin_can_still_cancel_a_running_booking(): void
    {
        $booking = $this->booking($this->resource(), now()->subMinute());
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['name' => RoleEnum::ADMIN]));

        $this->actingAs($admin)->deleteJson("/api/v1/bookings/{$booking->id}")->assertOk();

        $this->assertSame(BookingStatus::CANCELLED, $booking->fresh()->status);
    }

    // [check-in window after, no-show limit, minutes since start, expected]
    #[TestWith([45, 15, 20, BookingStatus::APPROVED])]
    #[TestWith([45, 15, 45, BookingStatus::APPROVED])]
    #[TestWith([45, 15, 46, BookingStatus::NO_SHOW])]
    #[TestWith([15, 30, 20, BookingStatus::APPROVED])]
    #[TestWith([15, 30, 31, BookingStatus::NO_SHOW])]
    public function test_no_show_waits_until_check_in_is_closed(int $checkInAfter, int $noShow, int $minutes, BookingStatus $expected): void
    {
        Setting::set('checkin_window_after_minutes', $checkInAfter, 'integer');
        Setting::set('check_in_window_minutes', $noShow, 'integer');
        $booking = $this->booking($this->resource(), now()->subMinutes($minutes));

        app()->call([new ExpireNoShowBookings, 'handle']);

        $this->assertSame($expected, $booking->fresh()->status);
    }

    public function test_recurring_request_returns_422_when_every_occurrence_is_refused(): void
    {
        $user = User::factory()->create();
        Suspension::create(['user_id' => $user->id, 'starts_at' => now()->subDay(), 'ends_at' => now()->addWeek()]);

        $this->actingAs($user)
            ->postJson('/api/v1/bookings/recurring', $this->recurring($this->resource()))
            ->assertStatus(422)
            ->assertJsonValidationErrors('user');

        $this->assertSame(0, BookingSeries::count());
    }

    public function test_recurring_request_returns_409_when_every_occurrence_conflicts(): void
    {
        $resource = $this->resource();
        foreach (range(0, 2) as $week) {
            $this->booking($resource, Carbon::parse('2026-12-02 03:00:00')->addWeeks($week));
        }

        $this->actingAs(User::factory()->create())
            ->postJson('/api/v1/bookings/recurring', $this->recurring($resource))
            ->assertStatus(409);

        $this->assertSame(0, BookingSeries::count());
    }

    public function test_recurring_request_skips_only_the_conflicting_occurrence(): void
    {
        $resource = $this->resource();
        $this->booking($resource, Carbon::parse('2026-12-09 03:00:00'));
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/bookings/recurring', $this->recurring($resource))
            ->assertCreated()
            ->assertJsonCount(2, 'bookings');
    }

    public function test_offset_timestamps_are_stored_as_the_same_instant(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/bookings', [
                'resource_id' => $this->resource()->id,
                'starts_at' => '2026-12-02T10:00:00+07:00',
                'ends_at' => '2026-12-02T11:00:00+07:00',
            ])
            ->assertCreated();

        $booking = Booking::sole();
        $this->assertSame('2026-12-02 03:00:00', $booking->starts_at->toDateTimeString());
        $this->assertSame('2026-12-02 04:00:00', $booking->ends_at->toDateTimeString());
    }

    public function test_offset_timestamp_conflicts_with_the_same_instant(): void
    {
        $resource = $this->resource();
        $this->booking($resource, Carbon::parse('2026-12-02 03:00:00'));

        $this->actingAs(User::factory()->create())
            ->postJson('/api/v1/bookings', [
                'resource_id' => $resource->id,
                'starts_at' => '2026-12-02T10:00:00+07:00',
                'ends_at' => '2026-12-02T11:00:00+07:00',
            ])
            ->assertStatus(409);
    }

    public function test_recurring_offset_timestamps_are_stored_as_the_same_instant(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/v1/bookings/recurring', [
                ...$this->recurring($this->resource()),
                'starts_at' => '2026-12-02T10:00:00+07:00',
                'ends_at' => '2026-12-02T11:00:00+07:00',
            ])
            ->assertCreated();

        $this->assertSame('2026-12-02 03:00:00', Booking::orderBy('starts_at')->first()->starts_at->toDateTimeString());
    }

    public function test_availability_filter_compares_offset_timestamps_by_instant(): void
    {
        $resource = $this->resource();
        $this->booking($resource, Carbon::parse('2026-12-02 03:00:00'));
        $query = http_build_query([
            'available_from' => '2026-12-02T10:00:00+07:00',
            'available_to' => '2026-12-02T11:00:00+07:00',
        ]);

        $this->actingAs(User::factory()->create())
            ->getJson("/api/v1/resources?{$query}")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    private function resource(): Resource
    {
        return Resource::create(['name' => 'Kamera', 'type' => 'asset', 'is_active' => true]);
    }

    /**
     * A one hour approved booking.
     */
    private function booking(Resource $resource, Carbon $startsAt): Booking
    {
        return Booking::create([
            'user_id' => User::factory()->create()->id,
            'resource_id' => $resource->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHour(),
            'status' => 'approved',
        ]);
    }

    /**
     * Three weekly occurrences from 2 December 03:00 UTC.
     *
     * @return array<string, mixed>
     */
    private function recurring(Resource $resource): array
    {
        return [
            'resource_id' => $resource->id,
            'starts_at' => '2026-12-02T03:00:00Z',
            'ends_at' => '2026-12-02T04:00:00Z',
            'occurrences' => 3,
            'conflict_policy' => 'skip',
        ];
    }
}
