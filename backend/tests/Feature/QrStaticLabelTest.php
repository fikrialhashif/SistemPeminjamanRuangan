<?php

namespace Tests\Feature;

use App\Enums\Role as RoleEnum;
use App\Models\Asset;
use App\Models\AssetLoan;
use App\Models\Booking;
use App\Models\Location;
use App\Models\Resource;
use App\Models\Role;
use App\Models\User;
use App\Services\FaceVerificationService;
use App\Services\QrTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QrStaticLabelTest extends TestCase
{
    use RefreshDatabase;

    public function test_room_token_is_deterministic_signed_and_bound_to_room(): void
    {
        $service = app(QrTokenService::class);
        $room = $this->room('Ruang Rapat A1');

        $token = $service->generate($room->id)['token'];

        $this->assertSame($token, $service->generate($room->id)['token']);
        $this->assertTrue($service->validate($token, $room->id, 1)['valid']);
        $this->assertSame('Resource mismatch', $service->validate($token, $room->id + 1, 1)['reason']);
        $this->assertSame('Invalid token signature', $service->validate($token.'x', $room->id, 1)['reason']);
    }

    public function test_asset_token_is_rejected_by_room_check_in(): void
    {
        $service = app(QrTokenService::class);
        $assetResource = $this->asset('Kamera DSLR', 'CAM-001');
        $room = $this->room('Ruang Rapat B1');

        $token = $service->generateForAsset($assetResource->asset)['token'];

        $this->assertTrue($service->validateForAsset($token, 'CAM-001')['valid']);
        $this->assertSame('Asset mismatch', $service->validateForAsset($token, 'CAM-002')['reason']);
        $this->assertSame('Resource mismatch', $service->validate($token, $room->id, 1)['reason']);
    }

    public function test_room_qr_page_is_printable_without_rotation(): void
    {
        $room = $this->room('Ruang Rapat A1');

        $this->get(route('resources.qr', $room))
            ->assertOk()
            ->assertSee('QR pintu · statis', false)
            ->assertSee('Cetak QR')
            ->assertSee('QR permanen · tidak perlu diganti', false)
            ->assertDontSee('http-equiv="refresh"', false)
            ->assertDontSee('Diperbarui otomatis', false);
    }

    public function test_asset_label_page_shows_asset_code(): void
    {
        $resource = $this->asset('Kamera DSLR', 'CAM-001');

        $this->get(route('resources.asset-label', $resource))
            ->assertOk()
            ->assertSee('Kode aset · statis', false)
            ->assertSee('Kode CAM-001', false)
            ->assertSee('Pindai saat serah-terima.', false);
    }

    public function test_static_room_token_passes_qr_factor_during_check_in(): void
    {
        $room = $this->room('Ruang Rapat A1');
        $user = User::factory()->create();
        $booking = Booking::create([
            'user_id' => $user->id,
            'resource_id' => $room->id,
            'title' => 'Rapat tim',
            'starts_at' => now()->addMinutes(5),
            'ends_at' => now()->addMinutes(65),
            'status' => 'approved',
        ]);
        $token = app(QrTokenService::class)->generate($room->id)['token'];

        $response = $this->actingAs($user)
            ->postJson("/api/v1/bookings/{$booking->id}/check-in", [
                'qr_token' => $token,
                'gps_latitude' => -6.2,
                'gps_longitude' => 106.8,
                'gps_accuracy' => 10,
                'gps_timestamp' => now()->toIso8601String(),
                'gps_is_mock' => false,
            ])
            ->assertOk()
            ->assertJsonPath('result', 'rejected');

        $reasons = json_encode($response->json('rejection_reasons'));
        $this->assertStringNotContainsString('qr:', (string) $reasons);
        $this->assertTrue($booking->checkInAttempts()->latest()->firstOrFail()->qr_valid);
    }

    public function test_handover_requires_manager_and_matching_asset_code(): void
    {
        $this->mock(FaceVerificationService::class)
            ->shouldReceive('verify')
            ->andReturn(['match' => true]);

        $resource = $this->asset('Kamera DSLR', 'CAM-001');
        $asset = $resource->asset;
        $manager = $this->userWithRole(RoleEnum::RESOURCE_MANAGER);
        $borrower = $this->userWithRole(RoleEnum::MEMBER);
        $loan = $this->loan($resource, $asset, $borrower);

        $payload = [
            'asset_code' => 'CAM-001',
            'face_image' => 'face.jpg',
            'gps_latitude' => -6.2,
            'gps_longitude' => 106.8,
            'gps_accuracy' => 10,
        ];

        $this->actingAs($borrower)
            ->postJson("/api/v1/loans/{$loan->id}/handover", $payload)
            ->assertForbidden();

        $this->actingAs($manager)
            ->postJson("/api/v1/loans/{$loan->id}/handover", ['asset_code' => 'CAM-999'] + $payload)
            ->assertStatus(422);

        $this->actingAs($manager)
            ->postJson("/api/v1/loans/{$loan->id}/handover", $payload)
            ->assertOk();

        $this->assertSame('handed_over', $loan->fresh()->status->value);
        $this->assertSame('static_label', $loan->events()->firstOrFail()->data['scan_source']);
    }

    private function userWithRole(RoleEnum $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => $role]));

        return $user;
    }

    private function room(string $name): Resource
    {
        return Resource::create([
            'name' => $name,
            'type' => 'room',
            'location_id' => $this->location()->id,
            'is_active' => true,
        ]);
    }

    private function asset(string $name, string $code): Resource
    {
        $resource = Resource::create([
            'name' => $name,
            'type' => 'asset',
            'location_id' => $this->location()->id,
            'is_active' => true,
        ]);
        $resource->asset()->create(['code' => $code, 'condition' => 'good']);

        return $resource->load('asset');
    }

    private function location(): Location
    {
        return Location::create([
            'name' => 'Gedung A',
            'latitude' => -6.2,
            'longitude' => 106.8,
        ]);
    }

    private function loan(Resource $resource, Asset $asset, User $borrower): AssetLoan
    {
        $booking = Booking::create([
            'user_id' => $borrower->id,
            'resource_id' => $resource->id,
            'title' => 'Pinjam kamera',
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHours(2),
            'status' => 'approved',
        ]);

        return AssetLoan::create([
            'booking_id' => $booking->id,
            'asset_id' => $asset->id,
            'user_id' => $borrower->id,
            'status' => 'reserved',
            'due_at' => now()->addHours(2),
        ]);
    }
}
