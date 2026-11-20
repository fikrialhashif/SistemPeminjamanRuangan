<?php

namespace Tests\Feature;

use App\Enums\Role as RoleEnum;
use App\Models\Category;
use App\Models\Facility;
use App\Models\Location;
use App\Models\Resource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_and_update_categories_scoped_by_type(): void
    {
        $admin = $this->userWithRole(RoleEnum::ADMIN);

        $this->actingAs($admin)
            ->post(route('admin.categories.store'), [
                'name' => 'Ruang rapat',
                'type' => 'room',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->post(route('admin.categories.store'), [
                'name' => 'Ruang rapat',
                'type' => 'asset',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->post(route('admin.categories.store'), [
                'name' => 'Ruang rapat',
                'type' => 'room',
            ])
            ->assertSessionHasErrors('name');

        $this->assertSame(2, Category::count());

        $category = Category::where('type', 'room')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.categories.update', $category), [
                'name' => 'Ruang Rapat Besar',
                'type' => 'room',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Ruang Rapat Besar', $category->fresh()->name);
    }

    public function test_admin_can_manage_facilities(): void
    {
        $admin = $this->userWithRole(RoleEnum::ADMIN);

        $this->actingAs($admin)
            ->post(route('admin.facilities.store'), ['name' => 'Proyektor'])
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->post(route('admin.facilities.store'), ['name' => 'Proyektor'])
            ->assertSessionHasErrors('name');

        $facility = Facility::where('name', 'Proyektor')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.facilities.update', $facility), ['name' => 'Proyektor Portabel'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Proyektor Portabel', $facility->fresh()->name);

        $this->actingAs($admin)
            ->delete(route('admin.facilities.destroy', $facility))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('facilities', ['id' => $facility->id]);
    }

    public function test_master_data_pages_are_admin_only(): void
    {
        $manager = $this->userWithRole(RoleEnum::RESOURCE_MANAGER);

        $this->actingAs($manager)->get(route('admin.categories'))->assertForbidden();
        $this->actingAs($manager)->get(route('admin.facilities'))->assertForbidden();
        $this->actingAs($manager)
            ->post(route('admin.categories.store'), ['name' => 'X', 'type' => 'room'])
            ->assertForbidden();
    }

    public function test_category_and_facility_in_use_cannot_be_deleted(): void
    {
        $admin = $this->userWithRole(RoleEnum::ADMIN);
        $category = Category::create(['name' => 'Ruang rapat', 'type' => 'room']);
        $facility = Facility::create(['name' => 'Pendingin Udara']);

        $resource = Resource::create([
            'name' => 'Ruang Rapat',
            'type' => 'room',
            'category_id' => $category->id,
            'location_id' => $this->location()->id,
        ]);
        $resource->room()->create(['capacity' => 10, 'facilities' => ['Pendingin Udara']]);

        $this->actingAs($admin)
            ->delete(route('admin.categories.destroy', $category))
            ->assertSessionHasErrors('category');

        $this->actingAs($admin)
            ->delete(route('admin.facilities.destroy', $facility))
            ->assertSessionHasErrors('facility');

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
        $this->assertDatabaseHas('facilities', ['id' => $facility->id]);
    }

    public function test_api_keeps_category_as_a_name_string(): void
    {
        $admin = $this->userWithRole(RoleEnum::ADMIN);
        $category = Category::create(['name' => 'Ruang rapat', 'type' => 'room']);

        Resource::create([
            'name' => 'Ruang Rapat 1',
            'type' => 'room',
            'category_id' => $category->id,
            'location_id' => $this->location()->id,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/resources?category=Ruang rapat')
            ->assertOk()
            ->assertJsonFragment(['category' => 'Ruang rapat'])
            ->assertJsonFragment(['category_id' => $category->id]);
    }

    private function userWithRole(RoleEnum $role): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => $role]);
        $user->roles()->attach($role);

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
}
