<?php

namespace Tests\Feature;

use App\Models\FaceEnrollment;
use App\Models\Resource;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingFaceEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_room_booking_requires_an_enrolled_face(): void
    {
        Setting::set('face_verification_enabled', true, 'boolean');
        $user = User::factory()->create();
        $room = $this->resource('room');

        $this->actingAs($user)
            ->postJson('/api/v1/bookings', $this->payload($room))
            ->assertStatus(422)
            ->assertJsonPath('code', 'face_enrollment_required');
    }

    public function test_room_booking_is_allowed_after_face_enrollment(): void
    {
        Setting::set('face_verification_enabled', true, 'boolean');
        $user = User::factory()->create();
        $room = $this->resource('room');

        FaceEnrollment::create([
            'user_id' => $user->id,
            'consent_given_at' => now(),
            'status' => 'enrolled',
            'samples_count' => 3,
        ]);

        $this->actingAs($user)
            ->postJson('/api/v1/bookings', $this->payload($room))
            ->assertCreated();
    }

    public function test_asset_booking_does_not_require_face_enrollment(): void
    {
        Setting::set('face_verification_enabled', true, 'boolean');
        $user = User::factory()->create();
        $asset = $this->resource('asset');

        $this->actingAs($user)
            ->postJson('/api/v1/bookings', $this->payload($asset))
            ->assertCreated();
    }

    private function resource(string $type): Resource
    {
        return Resource::create([
            'name' => $type === 'room' ? 'Ruang Rapat' : 'Proyektor',
            'type' => $type,
            'is_active' => true,
        ]);
    }

    private function payload(Resource $resource): array
    {
        return [
            'resource_id' => $resource->id,
            'starts_at' => now()->addDay()->toIso8601String(),
            'ends_at' => now()->addDay()->addHour()->toIso8601String(),
            'title' => 'Test booking',
        ];
    }
}
