<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\Role as RoleEnum;
use App\Jobs\ExpireNoShowBookings;
use App\Jobs\ExpirePendingApprovals;
use App\Models\Booking;
use App\Models\FaceEnrollment;
use App\Models\Location;
use App\Models\Resource;
use App\Models\Role;
use App\Models\User;
use App\Models\WaitlistEntry;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class BookingRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 08:00 WIB
        $this->travelTo(Carbon::parse('2026-12-01 01:00:00', 'UTC'));
    }

    #[TestWith(['2026-12-02T07:00:00+07:00', '2026-12-02T08:00:00+07:00', 201])]
    #[TestWith(['2026-12-02T21:00:00+07:00', '2026-12-02T22:00:00+07:00', 201])]
    #[TestWith(['2026-12-02T00:00:00Z', '2026-12-02T01:00:00Z', 201])]
    #[TestWith(['2026-12-02T06:30:00+07:00', '2026-12-02T07:30:00+07:00', 422])]
    #[TestWith(['2026-12-02T21:30:00+07:00', '2026-12-02T22:30:00+07:00', 422])]
    #[TestWith(['2026-12-02T02:00:00+07:00', '2026-12-02T03:00:00+07:00', 422])]
    #[TestWith(['2026-12-02T21:00:00+07:00', '2026-12-03T08:00:00+07:00', 422])]
    public function test_room_booking_must_fall_within_operating_hours(string $start, string $end, int $status): void
    {
        $room = $this->room(['max_duration_minutes' => 1440]);

        $response = $this->actingAs($this->enrolledUser())
            ->postJson('/api/v1/bookings', ['resource_id' => $room->id, 'starts_at' => $start, 'ends_at' => $end])
            ->assertStatus($status);

        if ($status === 422) {
            $response->assertJsonPath('errors.starts_at.0', 'Waktu pemesanan harus di dalam jam operasional (07:00-22:00 WIB).');
        }
    }

    public function test_cancelling_a_booking_offers_the_slot_to_the_first_in_line(): void
    {
        $booking = $this->booking($this->room());
        $first = $this->joinWaitlist(User::factory()->create(), $booking);
        $this->travel(1)->minutes();
        $second = $this->joinWaitlist(User::factory()->create(), $booking);

        $this->actingAs($booking->user)->deleteJson("/api/v1/bookings/{$booking->id}")->assertOk();

        $this->assertSame('offered', $first->fresh()->status);
        $this->assertSame('waiting', $second->fresh()->status);
    }

    public function test_rejecting_a_booking_offers_the_slot_to_the_waitlist(): void
    {
        $booking = $this->booking($this->room(), ['status' => 'pending']);
        $approval = $booking->approvals()->create(['level' => 1, 'status' => 'pending']);
        $entry = $this->joinWaitlist(User::factory()->create(), $booking);

        $this->actingAs($this->approver())->postJson("/api/v1/approvals/{$approval->id}/reject")->assertOk();

        $this->assertSame('offered', $entry->fresh()->status);
    }

    public function test_a_slot_that_already_started_is_not_offered(): void
    {
        $booking = $this->booking($this->room(), ['starts_at' => now()->subMinutes(30), 'ends_at' => now()->addMinutes(30)]);
        $entry = $this->joinWaitlist(User::factory()->create(), $booking);

        app()->call([new ExpireNoShowBookings, 'handle']);

        $this->assertSame(BookingStatus::NO_SHOW, $booking->fresh()->status);
        $this->assertSame('waiting', $entry->fresh()->status);
    }

    public function test_declining_an_offer_passes_it_to_the_next_in_line(): void
    {
        $booking = $this->booking($this->room());
        $first = $this->joinWaitlist(User::factory()->create(), $booking);
        $this->travel(1)->minutes();
        $second = $this->joinWaitlist(User::factory()->create(), $booking);
        $this->actingAs($booking->user)->deleteJson("/api/v1/bookings/{$booking->id}")->assertOk();

        $this->actingAs($first->user)->postJson("/api/v1/waitlist/{$first->id}/decline")->assertOk();

        $this->assertSame('offered', $second->fresh()->status);
    }

    public function test_claiming_an_offer_creates_a_booking(): void
    {
        $room = $this->room();
        $entry = $this->offeredEntry($room);

        $this->actingAs($entry->user)
            ->postJson("/api/v1/waitlist/{$entry->id}/claim")
            ->assertOk()
            ->assertJsonPath('status', 'claimed');

        $booking = Booking::where('user_id', $entry->user_id)->sole();
        $this->assertSame(BookingStatus::PENDING, $booking->status);
        $this->assertEquals($entry->desired_start, $booking->starts_at);
        $this->assertEquals($entry->desired_end, $booking->ends_at);
    }

    public function test_claim_returns_409_and_keeps_the_offer_when_the_slot_was_taken(): void
    {
        $room = $this->room();
        $entry = $this->offeredEntry($room);
        $this->booking($room, ['starts_at' => $entry->desired_start, 'ends_at' => $entry->desired_end]);

        $this->actingAs($entry->user)
            ->postJson("/api/v1/waitlist/{$entry->id}/claim")
            ->assertStatus(409);

        $this->assertSame('offered', $entry->fresh()->status);
        $this->assertSame(0, Booking::where('user_id', $entry->user_id)->count());
    }

    public function test_an_offer_cannot_be_claimed_twice(): void
    {
        $entry = $this->offeredEntry($this->room());
        $this->actingAs($entry->user)->postJson("/api/v1/waitlist/{$entry->id}/claim")->assertOk();

        $this->actingAs($entry->user)->postJson("/api/v1/waitlist/{$entry->id}/claim")->assertStatus(422);

        $this->assertSame(1, Booking::where('user_id', $entry->user_id)->count());
    }

    public function test_two_level_booking_can_be_approved_after_level_one_escalates(): void
    {
        $room = $this->room(['approval_path' => 'two_level']);
        $id = $this->actingAs($this->enrolledUser())
            ->postJson('/api/v1/bookings', [
                'resource_id' => $room->id,
                'starts_at' => '2026-12-10T03:00:00Z',
                'ends_at' => '2026-12-10T04:00:00Z',
            ])
            ->assertCreated()
            ->json('id');
        $booking = Booking::findOrFail($id);
        $this->travel(49)->hours();
        (new ExpirePendingApprovals)->handle();
        $levelTwo = $booking->approvals()->where('level', 2)->sole();
        $approver = $this->approver();

        $this->actingAs($approver)
            ->getJson('/api/v1/approvals/pending')
            ->assertOk()
            ->assertJsonPath('data.0.id', $levelTwo->id);
        $this->actingAs($approver)
            ->postJson("/api/v1/approvals/{$levelTwo->id}/approve")
            ->assertOk();

        $this->assertSame(BookingStatus::APPROVED, $booking->fresh()->status);
    }

    /**
     * @param  array<string, mixed>  $rules
     */
    private function room(array $rules = []): Resource
    {
        $location = Location::create([
            'name' => 'Gedung A',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'open_time' => '07:00:00',
            'close_time' => '22:00:00',
        ]);
        $room = Resource::create(['name' => 'Ruang Rapat', 'type' => 'room', 'location_id' => $location->id, 'is_active' => true]);
        $room->room()->create(['capacity' => 10]);
        $room->rules()->create($rules);

        return $room;
    }

    /**
     * An approved booking for tomorrow 10:00 WIB.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function booking(Resource $room, array $attributes = []): Booking
    {
        return Booking::create([
            'user_id' => User::factory()->create()->id,
            'resource_id' => $room->id,
            'starts_at' => '2026-12-02 03:00:00',
            'ends_at' => '2026-12-02 04:00:00',
            'status' => 'approved',
            ...$attributes,
        ]);
    }

    private function joinWaitlist(User $user, Booking $booking): WaitlistEntry
    {
        $id = $this->actingAs($user)
            ->postJson("/api/v1/bookings/{$booking->id}/waitlist")
            ->assertCreated()
            ->json('id');

        return WaitlistEntry::findOrFail($id);
    }

    private function offeredEntry(Resource $room): WaitlistEntry
    {
        return WaitlistEntry::create([
            'user_id' => $this->enrolledUser()->id,
            'resource_id' => $room->id,
            'desired_start' => '2026-12-02 03:00:00',
            'desired_end' => '2026-12-02 04:00:00',
            'status' => 'offered',
            'offer_expires_at' => now()->addMinutes(30),
        ]);
    }

    private function enrolledUser(): User
    {
        $user = User::factory()->create();
        FaceEnrollment::create(['user_id' => $user->id, 'consent_given_at' => now(), 'status' => 'enrolled']);

        return $user;
    }

    private function approver(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => RoleEnum::APPROVER]));

        return $user;
    }
}
