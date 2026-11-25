<?php

namespace Tests\Feature;

use App\Enums\ResourceType;
use App\Models\Booking;
use App\Models\Location;
use App\Models\Resource;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ResourceAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_room_availability_uses_room_hours_and_marks_bookings_as_dipesan(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $date = now()->toDateString();
        $location = Location::create([
            'name' => 'Gedung A',
            'latitude' => -6.2,
            'longitude' => 106.8,
        ]);
        $room = Resource::create([
            'name' => 'Ruang Rapat A1',
            'type' => ResourceType::ROOM,
            'location_id' => $location->id,
            'is_active' => true,
        ]);
        $room->room()->create([
            'capacity' => 20,
            'open_time' => '08:00:00',
            'close_time' => '12:00:00',
        ]);
        $room->rules()->create(['buffer_minutes' => 15]);

        Booking::create([
            'user_id' => User::factory()->create()->id,
            'resource_id' => $room->id,
            'starts_at' => Carbon::parse($date.' 09:00:00', 'Asia/Jakarta')->utc(),
            'ends_at' => Carbon::parse($date.' 10:00:00', 'Asia/Jakarta')->utc(),
            'status' => 'approved',
        ]);

        $this->getJson("/api/v1/resources/{$room->id}/availability?date={$date}")
            ->assertOk()
            ->assertJsonPath('open_time', '08:00')
            ->assertJsonPath('close_time', '12:00')
            ->assertJsonPath('hours_source', 'room')
            ->assertJsonCount(3, 'intervals')
            ->assertJsonPath('intervals.0.available', true)
            ->assertJsonPath('intervals.1.available', false)
            ->assertJsonPath('intervals.1.label', 'Dipesan')
            ->assertJsonPath('intervals.1.status_detail', 'approved')
            ->assertJsonPath('intervals.2.available', true);
    }

    public function test_asset_availability_uses_asset_location_hours(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $date = now()->toDateString();
        $location = Location::create([
            'name' => 'Peminjaman Aset',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'open_time' => '07:00:00',
            'close_time' => '22:00:00',
        ]);
        $asset = Resource::create([
            'name' => 'Kamera DSLR',
            'type' => ResourceType::ASSET,
            'location_id' => $location->id,
            'is_active' => true,
        ]);
        $asset->asset()->create(['code' => 'CAM-001', 'condition' => 'good']);
        $asset->rules()->create(['buffer_minutes' => 0]);

        Booking::create([
            'user_id' => User::factory()->create()->id,
            'resource_id' => $asset->id,
            'starts_at' => Carbon::parse($date.' 10:00:00', 'Asia/Jakarta')->utc(),
            'ends_at' => Carbon::parse($date.' 11:00:00', 'Asia/Jakarta')->utc(),
            'status' => 'pending',
        ]);

        $this->getJson("/api/v1/resources/{$asset->id}/availability?date={$date}")
            ->assertOk()
            ->assertJsonPath('open_time', '07:00')
            ->assertJsonPath('close_time', '22:00')
            ->assertJsonPath('hours_source', 'location')
            ->assertJsonCount(3, 'intervals')
            ->assertJsonPath('intervals.1.status_detail', 'pending')
            ->assertJsonPath('intervals.1.label', 'Dipesan');
    }

    public function test_seeder_moves_existing_assets_to_location_with_operating_hours(): void
    {
        $oldLocation = Location::create([
            'name' => 'Gedung A',
            'latitude' => -6.2,
            'longitude' => 106.8,
        ]);
        $asset = Resource::create([
            'name' => 'Kamera DSLR',
            'type' => ResourceType::ASSET,
            'location_id' => $oldLocation->id,
            'is_active' => true,
        ]);

        $this->seed(DatabaseSeeder::class);

        $asset->refresh();
        $this->assertSame('Peminjaman Aset', $asset->location->name);
        $this->assertSame('07:00:00', $asset->location->open_time);
        $this->assertSame('22:00:00', $asset->location->close_time);
    }

    public function test_availability_fails_when_operating_hours_are_missing(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $date = now()->toDateString();
        $location = Location::create([
            'name' => 'Tanpa jam',
            'latitude' => -6.2,
            'longitude' => 106.8,
        ]);
        $resource = Resource::create([
            'name' => 'Aset tanpa jam',
            'type' => ResourceType::ASSET,
            'location_id' => $location->id,
            'is_active' => true,
        ]);

        $this->getJson("/api/v1/resources/{$resource->id}/availability?date={$date}")
            ->assertStatus(422)
            ->assertJsonPath('errors.date.0', 'Jam operasional resource belum diatur.');
    }
}
