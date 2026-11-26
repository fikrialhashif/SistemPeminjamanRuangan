<?php

namespace Tests\Feature;

use App\Enums\Role as RoleEnum;
use App\Models\Location;
use App\Models\Resource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLocationHoursTest extends TestCase
{
    use RefreshDatabase;

    public function test_location_created_without_hours_gets_default_operating_hours(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)
            ->post(route('admin.locations.store'), $this->payload())
            ->assertSessionHasNoErrors();
        $location = Location::sole();
        $resource = Resource::create(['name' => 'Kamera', 'type' => 'asset', 'location_id' => $location->id]);

        $this->actingAs($admin)
            ->getJson("/api/v1/resources/{$resource->id}/availability?date=2026-12-02")
            ->assertOk()
            ->assertJsonPath('open_time', '07:00')
            ->assertJsonPath('close_time', '22:00');
    }

    public function test_admin_can_set_and_keep_operating_hours(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)
            ->post(route('admin.locations.store'), $this->payload(['open_time' => '08:30', 'close_time' => '17:00']))
            ->assertSessionHasNoErrors();
        $location = Location::sole();

        $this->actingAs($admin)
            ->put(route('admin.locations.update', $location), $this->payload(['name' => 'Gedung Lama']))
            ->assertSessionHasNoErrors();

        $location->refresh();
        $this->assertSame('08:30', substr($location->open_time, 0, 5));
        $this->assertSame('17:00', substr($location->close_time, 0, 5));
    }

    public function test_closing_time_must_be_after_opening_time(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.locations.store'), $this->payload(['open_time' => '17:00', 'close_time' => '08:00']))
            ->assertSessionHasErrors('close_time');

        $this->assertSame(0, Location::count());
    }

    public function test_location_form_offers_operating_hour_inputs(): void
    {
        Location::create(['name' => 'Gedung A', 'latitude' => -6.2, 'longitude' => 106.8, 'open_time' => '09:15:00', 'close_time' => '18:45:00']);

        $this->actingAs($this->admin())
            ->get(route('admin.locations'))
            ->assertOk()
            ->assertSee('name="open_time" value="09:15"', false)
            ->assertSee('name="close_time" value="18:45"', false);
    }

    public function test_location_created_through_the_api_gets_default_operating_hours(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/api/v1/locations', ['name' => 'Gedung API', 'latitude' => -6.2, 'longitude' => 106.8])
            ->assertCreated()
            ->assertJsonPath('open_time', '07:00')
            ->assertJsonPath('close_time', '22:00');
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function payload(array $attributes = []): array
    {
        return [
            'name' => 'Gedung Baru',
            'geofence_type' => 'radius',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius' => 100,
            ...$attributes,
        ];
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => RoleEnum::ADMIN]));

        return $user;
    }
}
