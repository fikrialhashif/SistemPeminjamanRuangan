<?php

namespace Tests\Feature;

use App\Enums\Role as RoleEnum;
use App\Models\Booking;
use App\Models\Location;
use App\Models\Resource;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Strike;
use App\Models\User;
use App\Services\GpsValidator;
use App\Services\QrTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class CheckInSecurityTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['resources.qr'])]
    #[TestWith(['resources.asset-label'])]
    public function test_qr_pages_redirect_guests_to_login(string $route): void
    {
        $this->get(route($route, $this->asset()))
            ->assertRedirect(route('admin.login'));
    }

    #[TestWith(['resources.qr'])]
    #[TestWith(['resources.asset-label'])]
    public function test_qr_pages_forbid_users_without_resource_permission(string $route): void
    {
        $this->actingAs($this->userWithRole(RoleEnum::MEMBER))
            ->get(route($route, $this->asset()))
            ->assertForbidden();
    }

    public function test_resource_manager_can_print_a_door_qr(): void
    {
        $this->actingAs($this->userWithRole(RoleEnum::RESOURCE_MANAGER))
            ->get(route('resources.qr', $this->asset()))
            ->assertOk()
            ->assertSee('Cetak QR');
    }

    #[TestWith([-3600, false])]
    #[TestWith([-31, false])]
    #[TestWith([-5, true])]
    #[TestWith([3600, false])]
    public function test_gps_reading_must_be_fresh(int $offsetSeconds, bool $valid): void
    {
        $this->freezeTime();

        $result = app(GpsValidator::class)->validate(
            $this->location(),
            -6.2,
            106.8,
            5,
            now()->addSeconds($offsetSeconds)->toIso8601String(),
            false,
        );

        $this->assertSame($valid, $result['valid']);
    }

    public function test_repeated_check_in_failures_issue_only_one_strike_per_booking(): void
    {
        Setting::set('face_verification_enabled', false, 'boolean');
        $user = User::factory()->create();
        $resource = $this->asset();
        $booking = Booking::create([
            'user_id' => $user->id,
            'resource_id' => $resource->id,
            'starts_at' => now(),
            'ends_at' => now()->addHour(),
            'status' => 'approved',
        ]);
        $evidence = [
            'qr_token' => app(QrTokenService::class)->generate($resource->id)['token'],
            'gps_latitude' => -6.2,
            'gps_longitude' => 106.8,
            'gps_accuracy' => 5,
            'gps_timestamp' => now()->toIso8601String(),
            'gps_is_mock' => true,
        ];

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->actingAs($user)
                ->postJson("/api/v1/bookings/{$booking->id}/check-in", $evidence)
                ->assertOk()
                ->assertJsonPath('result', 'rejected');
        }

        $this->assertSame(1, Strike::where('user_id', $user->id)->count());
    }

    private function userWithRole(RoleEnum $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => $role]));

        return $user;
    }

    private function location(): Location
    {
        return Location::create([
            'name' => 'Gedung A',
            'latitude' => -6.2,
            'longitude' => 106.8,
        ]);
    }

    private function asset(): Resource
    {
        $resource = Resource::create([
            'name' => 'Kamera DSLR',
            'type' => 'asset',
            'location_id' => $this->location()->id,
            'is_active' => true,
        ]);
        $resource->asset()->create(['code' => 'CAM-001', 'condition' => 'good']);

        return $resource;
    }
}
