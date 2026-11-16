<?php

namespace Database\Seeders;

use App\Enums\ResourceType;
use App\Enums\Role as RoleEnum;
use App\Models\Department;
use App\Models\Location;
use App\Models\Resource;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $roles = collect(RoleEnum::cases())->mapWithKeys(function (RoleEnum $r) {
            return [$r->value => Role::firstOrCreate(['name' => $r->value])];
        });

        $departments = collect([
            ['name' => 'Teknik Informatika', 'code' => 'TI'],
            ['name' => 'Sistem Informasi', 'code' => 'SI'],
            ['name' => 'Manajemen', 'code' => 'MN'],
        ])->map(fn ($d) => Department::firstOrCreate(['code' => $d['code']], $d));

        $locations = collect([
            ['name' => 'Gedung A', 'address' => 'Lantai 1, Gedung A', 'latitude' => -6.2088000, 'longitude' => 106.8456000, 'radius' => 100],
            ['name' => 'Gedung B', 'address' => 'Lantai 2, Gedung B', 'latitude' => -6.2092000, 'longitude' => 106.8460000, 'radius' => 100],
            ['name' => 'Gedung C', 'address' => 'Lantai 1, Gedung C', 'latitude' => -6.2095000, 'longitude' => 106.8465000, 'radius' => 150],
        ])->map(fn ($l) => Location::firstOrCreate(['name' => $l['name']], $l));

        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Admin User', 'password' => Hash::make('password'), 'department_id' => $departments[0]->id],
        );
        $admin->roles()->syncWithoutDetaching([$roles[RoleEnum::ADMIN->value]->id]);

        $approver = User::firstOrCreate(
            ['email' => 'approver@example.com'],
            ['name' => 'Approver User', 'password' => Hash::make('password'), 'department_id' => $departments[0]->id],
        );
        $approver->roles()->syncWithoutDetaching([$roles[RoleEnum::APPROVER->value]->id]);

        $manager = User::firstOrCreate(
            ['email' => 'manager@example.com'],
            ['name' => 'Resource Manager', 'password' => Hash::make('password'), 'department_id' => $departments[1]->id],
        );
        $manager->roles()->syncWithoutDetaching([$roles[RoleEnum::RESOURCE_MANAGER->value]->id]);

        $member = User::firstOrCreate(
            ['email' => 'member@example.com'],
            ['name' => 'Member User', 'password' => Hash::make('password'), 'department_id' => $departments[2]->id],
        );
        $member->roles()->syncWithoutDetaching([$roles[RoleEnum::MEMBER->value]->id]);

        $room1 = Resource::firstOrCreate(
            ['name' => 'Ruang Rapat A1'],
            ['type' => ResourceType::ROOM, 'location_id' => $locations[0]->id, 'category' => 'meeting_room', 'is_active' => true],
        );
        $room1->room()->firstOrCreate(['resource_id' => $room1->id], ['capacity' => 20, 'facilities' => json_encode(['projector', 'whiteboard', 'ac'])]);
        $room1->rules()->firstOrCreate(['resource_id' => $room1->id], [
            'buffer_minutes' => 15,
            'min_duration_minutes' => 30,
            'max_duration_minutes' => 240,
            'advance_booking_days' => 14,
            'approval_path' => 'one_level',
            'cancellation_minutes_before' => 60,
        ]);

        $room2 = Resource::firstOrCreate(
            ['name' => 'Lab Komputer B2'],
            ['type' => ResourceType::ROOM, 'location_id' => $locations[1]->id, 'category' => 'lab', 'is_active' => true],
        );
        $room2->room()->firstOrCreate(['resource_id' => $room2->id], ['capacity' => 40, 'facilities' => json_encode(['computers', 'projector', 'ac'])]);
        $room2->rules()->firstOrCreate(['resource_id' => $room2->id], [
            'buffer_minutes' => 10,
            'min_duration_minutes' => 60,
            'max_duration_minutes' => 180,
            'advance_booking_days' => 7,
            'approval_path' => 'none',
            'cancellation_minutes_before' => 30,
        ]);

        $asset1 = Resource::firstOrCreate(
            ['name' => 'Proyektor Portable'],
            ['type' => ResourceType::ASSET, 'location_id' => $locations[2]->id, 'category' => 'electronics', 'is_active' => true],
        );
        $asset1->asset()->firstOrCreate(['resource_id' => $asset1->id], ['code' => 'PRJ-001', 'condition' => 'good']);
        $asset1->rules()->firstOrCreate(['resource_id' => $asset1->id], [
            'buffer_minutes' => 0,
            'min_duration_minutes' => 60,
            'max_duration_minutes' => 480,
            'advance_booking_days' => 7,
            'approval_path' => 'one_level',
            'cancellation_minutes_before' => 60,
        ]);

        $asset2 = Resource::firstOrCreate(
            ['name' => 'Kamera DSLR'],
            ['type' => ResourceType::ASSET, 'location_id' => $locations[2]->id, 'category' => 'electronics', 'is_active' => true],
        );
        $asset2->asset()->firstOrCreate(['resource_id' => $asset2->id], ['code' => 'CAM-001', 'condition' => 'good']);
        $asset2->rules()->firstOrCreate(['resource_id' => $asset2->id], [
            'buffer_minutes' => 0,
            'min_duration_minutes' => 60,
            'max_duration_minutes' => 1440,
            'advance_booking_days' => 3,
            'approval_path' => 'two_level',
            'cancellation_minutes_before' => 120,
        ]);

        Setting::set('check_in_window_minutes', 15, 'integer');
        Setting::set('strike_window_days', 30, 'integer');
        Setting::set('strike_threshold', 3, 'integer');
        Setting::set('suspension_duration_days', 7, 'integer');
        Setting::set('waitlist_offer_minutes', 30, 'integer');
    }
}
