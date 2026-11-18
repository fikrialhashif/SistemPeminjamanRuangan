<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\CheckInResult;
use App\Models\Booking;
use App\Models\CheckInAttempt;
use App\Models\FaceEnrollment;
use App\Models\Location;
use App\Models\Resource;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingApprovalAndDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_waits_for_approver_even_without_configured_approval_path(): void
    {
        [$user, $room] = $this->userWithEnrolledFaceAndRoom();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/bookings', $this->payload($room))
            ->assertCreated();

        $booking = Booking::findOrFail($response->json('id'));

        $this->assertSame(BookingStatus::PENDING, $booking->status);
        $this->assertCount(1, $booking->approvals);
        $this->assertSame('pending', $booking->approvals->first()->status);
    }

    public function test_check_in_is_rejected_before_approver_approval(): void
    {
        [$user, $room] = $this->userWithEnrolledFaceAndRoom();
        $booking = $this->createBooking($user, $room);

        $this->actingAs($user)
            ->postJson("/api/v1/bookings/{$booking->id}/check-in", $this->evidence())
            ->assertStatus(422)
            ->assertJsonPath('errors.status.0', 'Booking belum disetujui oleh approver.');
    }

    public function test_detail_contains_resource_location_and_check_in_history(): void
    {
        [$user, $room] = $this->userWithEnrolledFaceAndRoom();
        $booking = $this->createBooking($user, $room);

        CheckInAttempt::create([
            'booking_id' => $booking->id,
            'user_id' => $user->id,
            'result' => CheckInResult::ACCEPTED,
            'qr_valid' => true,
            'gps_valid' => true,
            'face_valid' => true,
            'gps_latitude' => 0.5677159,
            'gps_longitude' => 101.4227797,
            'gps_accuracy' => 13.44,
            'gps_timestamp' => now(),
            'face_score' => 0.8599,
        ]);

        $this->actingAs($user)
            ->getJson("/api/v1/bookings/{$booking->id}")
            ->assertOk()
            ->assertJsonPath('resource.location.name', 'Gedung Utama')
            ->assertJsonPath('check_in_attempts.0.result', 'accepted')
            ->assertJsonPath('check_in_attempts.0.qr_valid', true);
    }

    /**
     * @return array{0: User, 1: resource}
     */
    private function userWithEnrolledFaceAndRoom(): array
    {
        Setting::set('face_verification_enabled', true, 'boolean');

        $user = User::factory()->create();
        FaceEnrollment::create([
            'user_id' => $user->id,
            'consent_given_at' => now(),
            'status' => 'enrolled',
            'samples_count' => 3,
        ]);

        $location = Location::create([
            'name' => 'Gedung Utama',
            'latitude' => 0.5677159,
            'longitude' => 101.4227797,
        ]);
        $room = Resource::create([
            'name' => 'Ruang Rapat A1',
            'type' => 'room',
            'location_id' => $location->id,
            'is_active' => true,
        ]);

        return [$user, $room];
    }

    private function createBooking(User $user, Resource $room): Booking
    {
        $response = $this->actingAs($user)
            ->postJson('/api/v1/bookings', $this->payload($room))
            ->assertCreated();

        return Booking::findOrFail($response->json('id'));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Resource $room): array
    {
        return [
            'resource_id' => $room->id,
            'starts_at' => now()->addMinutes(5)->toIso8601String(),
            'ends_at' => now()->addMinutes(65)->toIso8601String(),
            'title' => 'Rapat tim',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function evidence(): array
    {
        return [
            'qr_token' => 'test-token',
            'gps_latitude' => 0.5677159,
            'gps_longitude' => 101.4227797,
            'gps_accuracy' => 13.44,
            'gps_timestamp' => now()->toIso8601String(),
            'gps_is_mock' => false,
        ];
    }
}
