<?php

namespace Tests\Feature;

use App\Enums\Role as RoleEnum;
use App\Models\AssetLoan;
use App\Models\Booking;
use App\Models\CheckInAttempt;
use App\Models\FaceEnrollment;
use App\Models\Location;
use App\Models\Resource;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Strike;
use App\Models\User;
use App\Services\QrTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class CheckInRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeTime();
        Http::preventStrayRequests();
    }

    #[TestWith([['gps_latitude' => 1.0]])]
    #[TestWith([['qr_token' => 'palsu']])]
    public function test_failed_location_or_qr_is_rejected_even_when_the_face_service_is_down(array $evidence): void
    {
        Http::fake(['*/verify' => Http::response('boom', 500)]);
        $booking = $this->booking($this->room(), $this->enrolledUser());
        cache()->put("face_challenge:{$booking->user_id}", 'turn', 300);

        $this->checkIn($booking, ['face_image' => 'a', 'face_front_image' => 'b', ...$evidence])
            ->assertOk()
            ->assertJsonPath('result', 'rejected');
    }

    public function test_unavailable_face_service_alone_needs_manual_review(): void
    {
        Http::fake(['*/verify' => Http::response('boom', 500)]);
        $booking = $this->booking($this->room(), $this->enrolledUser());
        cache()->put("face_challenge:{$booking->user_id}", 'turn', 300);

        $this->checkIn($booking, ['face_image' => 'a', 'face_front_image' => 'b'])
            ->assertOk()
            ->assertJsonPath('result', 'needs_manual_review');
    }

    public function test_room_booking_requires_enrollment_even_when_the_asset_setting_is_off(): void
    {
        Setting::set('face_verification_enabled', false, 'boolean');

        $this->actingAs(User::factory()->create())
            ->postJson('/api/v1/bookings', [
                'resource_id' => $this->room()->id,
                'starts_at' => now()->addDay()->setTime(3, 0)->toIso8601String(),
                'ends_at' => now()->addDay()->setTime(4, 0)->toIso8601String(),
            ])
            ->assertStatus(422);

        $this->assertSame(0, Booking::count());
    }

    public function test_settings_page_says_the_face_setting_only_covers_assets(): void
    {
        $this->actingAs($this->userWithRole(RoleEnum::ADMIN))
            ->get(route('admin.settings'))
            ->assertOk()
            ->assertSee('Wajib verifikasi wajah untuk aset')
            ->assertSee('Ruangan selalu memerlukan verifikasi wajah.');
    }

    public function test_check_in_returns_422_when_the_room_has_no_location(): void
    {
        $room = Resource::create(['name' => 'Tanpa lokasi', 'type' => 'room', 'is_active' => true]);
        $booking = $this->booking($room, $this->enrolledUser());

        $this->checkIn($booking)
            ->assertStatus(422)
            ->assertJsonValidationErrors('location');

        $this->assertSame(0, CheckInAttempt::count());
        $this->assertSame(0, Strike::count());
    }

    public function test_api_refuses_to_delete_a_location_that_is_still_used(): void
    {
        $location = $this->room()->location;

        $this->actingAs($this->userWithRole(RoleEnum::ADMIN))
            ->deleteJson("/api/v1/locations/{$location->id}")
            ->assertStatus(422);

        $this->assertNotNull($location->fresh());
    }

    #[TestWith(['false', false])]
    #[TestWith(['0', false])]
    #[TestWith([false, false])]
    #[TestWith(['true', true])]
    public function test_settings_api_stores_booleans_by_meaning(string|bool $value, bool $expected): void
    {
        $this->actingAs($this->userWithRole(RoleEnum::ADMIN))
            ->putJson('/api/v1/settings/face_verification_enabled', ['key' => 'face_verification_enabled', 'value' => $value, 'type' => 'boolean'])
            ->assertOk();

        $this->assertSame($expected, Setting::get('face_verification_enabled'));
    }

    public function test_settings_api_keeps_the_existing_type_when_none_is_sent(): void
    {
        Setting::set('face_verification_enabled', true, 'boolean');

        $this->actingAs($this->userWithRole(RoleEnum::ADMIN))
            ->putJson('/api/v1/settings/face_verification_enabled', ['key' => 'face_verification_enabled', 'value' => 'false'])
            ->assertOk();

        $this->assertFalse(Setting::get('face_verification_enabled'));
    }

    #[TestWith([500])]
    #[TestWith([0])]
    public function test_handover_returns_422_when_the_face_service_is_unavailable(int $status): void
    {
        Http::fake(['*/verify' => $status === 0 ? Http::failedConnection() : Http::response('boom', $status)]);
        $resource = Resource::create(['name' => 'Kamera', 'type' => 'asset', 'location_id' => $this->location()->id, 'is_active' => true]);
        $asset = $resource->asset()->create(['code' => 'CAM-001']);
        $booking = $this->booking($resource, User::factory()->create());
        $loan = AssetLoan::create(['booking_id' => $booking->id, 'asset_id' => $asset->id, 'user_id' => $booking->user_id, 'status' => 'reserved']);

        $this->actingAs($this->userWithRole(RoleEnum::RESOURCE_MANAGER))
            ->postJson("/api/v1/loans/{$loan->id}/handover", [
                'asset_token' => app(QrTokenService::class)->generateForAsset($asset)['token'],
                'face_image' => 'face',
                'gps_latitude' => -6.2,
                'gps_longitude' => 106.8,
                'gps_accuracy' => 5,
            ])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'face')
            ->assertJsonPath('message', 'Layanan wajah tidak tersedia. Gunakan override manual.');
    }

    /**
     * @param  array<string, mixed>  $evidence
     */
    private function checkIn(Booking $booking, array $evidence = []): mixed
    {
        return $this->actingAs($booking->user)->postJson("/api/v1/bookings/{$booking->id}/check-in", [
            'qr_token' => app(QrTokenService::class)->generate($booking->resource_id)['token'],
            'gps_latitude' => -6.2,
            'gps_longitude' => 106.8,
            'gps_accuracy' => 5,
            'gps_timestamp' => now()->toIso8601String(),
            'gps_is_mock' => false,
            ...$evidence,
        ]);
    }

    private function booking(Resource $resource, User $user): Booking
    {
        return Booking::create([
            'user_id' => $user->id,
            'resource_id' => $resource->id,
            'starts_at' => now(),
            'ends_at' => now()->addHour(),
            'status' => 'approved',
        ]);
    }

    private function location(): Location
    {
        return Location::create(['name' => 'Gedung A', 'latitude' => -6.2, 'longitude' => 106.8]);
    }

    private function room(): Resource
    {
        return Resource::create(['name' => 'Ruang Rapat', 'type' => 'room', 'location_id' => $this->location()->id, 'is_active' => true]);
    }

    private function enrolledUser(): User
    {
        $user = User::factory()->create();
        FaceEnrollment::create(['user_id' => $user->id, 'consent_given_at' => now(), 'status' => 'enrolled']);

        return $user;
    }

    private function userWithRole(RoleEnum $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => $role]));

        return $user;
    }
}
