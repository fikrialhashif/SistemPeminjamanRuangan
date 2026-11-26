<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\CheckInAttempt;
use App\Models\Location;
use App\Models\Resource;
use App\Models\Strike;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

/**
 * The app asks the server about the location before it lets the user continue checking in.
 */
class CheckInLocationTest extends TestCase
{
    use RefreshDatabase;

    private const LATITUDE = 0.5118332;

    private const LONGITUDE = 101.4490991;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeTime();
    }

    public function test_reading_inside_the_radius_is_allowed(): void
    {
        $booking = $this->booking();

        $this->checkLocation($booking)
            ->assertOk()
            ->assertExactJson([
                'valid' => true,
                'rejection_reasons' => [],
                'distance_meters' => 0,
                'radius_meters' => 100,
            ]);
    }

    public function test_reading_outside_the_radius_is_refused_with_the_distance(): void
    {
        $booking = $this->booking();

        // 0.002 degrees of latitude is about 222 m.
        $response = $this->checkLocation($booking, ['gps_latitude' => self::LATITUDE + 0.002])
            ->assertOk()
            ->assertJsonPath('valid', false)
            ->assertJsonPath('distance_meters', 222)
            ->assertJsonPath('radius_meters', 100);

        $this->assertStringStartsWith('gps: Distance', $response->json('rejection_reasons.0'));
    }

    public function test_polygon_area_reports_no_radius(): void
    {
        $booking = $this->booking(['polygon' => [
            ['lat' => 1.0, 'lng' => 101.0],
            ['lat' => 1.0, 'lng' => 101.1],
            ['lat' => 1.1, 'lng' => 101.1],
        ]]);

        $this->checkLocation($booking)
            ->assertOk()
            ->assertJsonPath('valid', false)
            ->assertJsonPath('distance_meters', null)
            ->assertJsonPath('radius_meters', null)
            ->assertJsonPath('rejection_reasons.0', 'gps: Outside geofence polygon');
    }

    #[TestWith([['gps_is_mock' => true]])]
    #[TestWith([['gps_accuracy' => 500]])]
    public function test_untrustworthy_reading_is_refused_even_inside_the_radius(array $reading): void
    {
        $this->checkLocation($this->booking(), $reading)
            ->assertOk()
            ->assertJsonPath('valid', false);
    }

    public function test_checking_the_location_records_no_attempt_and_no_strike(): void
    {
        $booking = $this->booking();

        foreach (range(1, 4) as $attempt) {
            $this->checkLocation($booking, ['gps_latitude' => 1.0])->assertOk();
        }

        $this->assertSame(0, CheckInAttempt::count());
        $this->assertSame(0, Strike::count());
    }

    public function test_returns_422_for_another_users_booking(): void
    {
        $booking = $this->booking();

        $this->checkLocation($booking, as: User::factory()->create())
            ->assertStatus(422)
            ->assertJsonValidationErrors('booking');
    }

    public function test_returns_422_when_the_booking_is_not_approved(): void
    {
        $booking = $this->booking();
        $booking->update(['status' => 'pending']);

        $this->checkLocation($booking)
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    public function test_returns_422_when_the_reading_is_incomplete(): void
    {
        $booking = $this->booking();

        $this->actingAs($booking->user)
            ->postJson("/api/v1/bookings/{$booking->id}/location-check", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['gps_latitude', 'gps_longitude', 'gps_accuracy', 'gps_timestamp', 'gps_is_mock']);
    }

    /**
     * @param  array<string, mixed>  $reading
     */
    private function checkLocation(Booking $booking, array $reading = [], ?User $as = null): mixed
    {
        return $this->actingAs($as ?? $booking->user)
            ->postJson("/api/v1/bookings/{$booking->id}/location-check", [
                'gps_latitude' => self::LATITUDE,
                'gps_longitude' => self::LONGITUDE,
                'gps_accuracy' => 8,
                'gps_timestamp' => now()->toIso8601String(),
                'gps_is_mock' => false,
                ...$reading,
            ]);
    }

    /**
     * @param  array<string, mixed>  $location
     */
    private function booking(array $location = []): Booking
    {
        $location = Location::create([
            'name' => 'Gedung A',
            'latitude' => self::LATITUDE,
            'longitude' => self::LONGITUDE,
            'radius' => 100,
            ...$location,
        ]);
        $room = Resource::create(['name' => 'Ruang Rapat', 'type' => 'room', 'location_id' => $location->id]);

        return Booking::create([
            'user_id' => User::factory()->create()->id,
            'resource_id' => $room->id,
            'starts_at' => now(),
            'ends_at' => now()->addHour(),
            'status' => 'approved',
        ]);
    }
}
