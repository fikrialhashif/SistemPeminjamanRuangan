<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\Role as RoleEnum;
use App\Models\Approval;
use App\Models\AssetLoan;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Category;
use App\Models\ConditionReport;
use App\Models\Facility;
use App\Models\LoanEvent;
use App\Models\Location;
use App\Models\Resource;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_room_with_details_and_booking_rules(): void
    {
        $location = $this->location();
        $category = Category::create(['name' => 'Ruang rapat', 'type' => 'room']);
        collect(['Pendingin Udara', 'Proyektor', 'Papan Tulis'])->each(
            fn (string $name) => Facility::create(['name' => $name]),
        );

        $this->actingAs($this->userWithRole(RoleEnum::ADMIN))
            ->post(route('admin.resources.store'), [
                'name' => 'Ruang Rapat 2.04',
                'type' => 'room',
                'location_id' => $location->id,
                'category_id' => $category->id,
                'capacity' => 12,
                'facilities' => ['Pendingin Udara', 'Proyektor', 'Papan Tulis'],
                'open_time' => '08:00',
                'close_time' => '17:00',
                'buffer_minutes' => 10,
                'min_duration_minutes' => 30,
                'max_duration_minutes' => 240,
                'advance_booking_days' => 14,
                'approval_path' => 'one_level',
                'cancellation_minutes_before' => 120,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $resource = Resource::where('name', 'Ruang Rapat 2.04')->firstOrFail();

        $this->assertSame(12, $resource->room->capacity);
        $this->assertSame(['Pendingin Udara', 'Proyektor', 'Papan Tulis'], $resource->room->facilities);
        $this->assertSame('Ruang rapat', $resource->category->name);
        $this->assertSame(10, $resource->rules->buffer_minutes);
        $this->assertSame('one_level', $resource->rules->approval_path->value);
    }

    public function test_admin_can_create_an_asset_with_code_and_condition(): void
    {
        $location = $this->location();
        $category = Category::create(['name' => 'Kamera', 'type' => 'asset']);

        $this->actingAs($this->userWithRole(RoleEnum::ADMIN))
            ->post(route('admin.resources.store'), [
                'name' => 'Kamera Sony',
                'type' => 'asset',
                'location_id' => $location->id,
                'category_id' => $category->id,
                'code' => 'CAM-001',
                'condition' => 'good',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $resource = Resource::where('name', 'Kamera Sony')->firstOrFail();

        $this->assertSame('CAM-001', $resource->asset->code);
        $this->assertSame('good', $resource->asset->condition);
    }

    public function test_only_approvers_can_decide_approvals_and_levels_must_be_sequential(): void
    {
        $booking = $this->booking();
        $firstLevel = Approval::create(['booking_id' => $booking->id, 'level' => 1, 'status' => 'pending']);
        $secondLevel = Approval::create(['booking_id' => $booking->id, 'level' => 2, 'status' => 'pending']);

        $this->actingAs($this->userWithRole(RoleEnum::RESOURCE_MANAGER))
            ->post(route('admin.approvals.approve', $firstLevel))
            ->assertForbidden();

        $approver = $this->userWithRole(RoleEnum::APPROVER);

        $this->actingAs($approver)
            ->post(route('admin.approvals.approve', $secondLevel))
            ->assertSessionHasErrors('approval');

        $this->assertSame('pending', $secondLevel->fresh()->status);

        $this->actingAs($approver)
            ->post(route('admin.approvals.approve', $firstLevel))
            ->assertSessionHasNoErrors();

        $this->assertSame('approved', $firstLevel->fresh()->status);
        $this->assertSame(BookingStatus::PENDING, $booking->fresh()->status);

        $this->actingAs($approver)
            ->post(route('admin.approvals.approve', $secondLevel))
            ->assertSessionHasNoErrors();

        $this->assertSame('approved', $secondLevel->fresh()->status);
        $this->assertSame(BookingStatus::APPROVED, $booking->fresh()->status);
    }

    public function test_approval_page_shows_booking_schedule(): void
    {
        $booking = $this->booking();
        Approval::create(['booking_id' => $booking->id, 'level' => 1, 'status' => 'pending']);

        $this->actingAs($this->userWithRole(RoleEnum::APPROVER))
            ->get(route('admin.approvals'))
            ->assertOk()
            ->assertSee('Jadwal')
            // Stored as 10:00-12:00 UTC, shown in WIB.
            ->assertSee('17:00')
            ->assertSee('19:00');
    }

    public function test_audit_trail_records_changes_without_hidden_credentials(): void
    {
        $admin = $this->userWithRole(RoleEnum::ADMIN);

        $this->actingAs($admin);
        $admin->update(['name' => 'Nama Baru']);

        $log = AuditLog::where('auditable_type', User::class)
            ->where('auditable_id', $admin->id)
            ->where('action', 'updated')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('Nama Baru', $log->new_values['name']);
        $this->assertArrayNotHasKey('password', $log->new_values);
        $this->assertArrayNotHasKey('remember_token', $log->new_values);
    }

    public function test_admin_can_update_toggle_and_delete_a_resource(): void
    {
        $admin = $this->userWithRole(RoleEnum::ADMIN);
        $location = $this->location();
        $category = Category::create(['name' => 'Ruang rapat', 'type' => 'room']);
        collect(['Pendingin Udara', 'Proyektor'])->each(fn (string $name) => Facility::create(['name' => $name]));
        $resource = Resource::create([
            'name' => 'Ruang Awal',
            'type' => 'room',
            'location_id' => $location->id,
        ]);
        $resource->room()->create(['capacity' => 8, 'facilities' => ['Pendingin Udara']]);
        $resource->rules()->create([
            'buffer_minutes' => 0,
            'min_duration_minutes' => 30,
            'max_duration_minutes' => 120,
            'advance_booking_days' => 7,
            'approval_path' => 'none',
            'cancellation_minutes_before' => 60,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.resources'))
            ->assertOk()
            ->assertSee('Tambah ruangan atau aset');

        $this->actingAs($admin)
            ->get(route('admin.resources.edit', $resource))
            ->assertOk()
            ->assertSee('Ruang Awal');

        $this->actingAs($admin)
            ->put(route('admin.resources.update', $resource), [
                'name' => 'Ruang Baru',
                'type' => 'room',
                'location_id' => $location->id,
                'category_id' => $category->id,
                'capacity' => 24,
                'facilities' => ['Pendingin Udara', 'Proyektor'],
                'buffer_minutes' => 15,
                'min_duration_minutes' => 60,
                'max_duration_minutes' => 240,
                'advance_booking_days' => 14,
                'approval_path' => 'two_level',
                'cancellation_minutes_before' => 120,
            ])
            ->assertRedirect(route('admin.resources'))
            ->assertSessionHasNoErrors();

        $resource->refresh()->load(['room', 'rules', 'category']);

        $this->assertSame('Ruang Baru', $resource->name);
        $this->assertSame(24, $resource->room->capacity);
        $this->assertSame(['Pendingin Udara', 'Proyektor'], $resource->room->facilities);
        $this->assertSame('Ruang rapat', $resource->category->name);
        $this->assertSame(15, $resource->rules->buffer_minutes);
        $this->assertSame('two_level', $resource->rules->approval_path->value);

        $this->actingAs($admin)
            ->patch(route('admin.resources.toggle', $resource))
            ->assertRedirect();

        $this->assertFalse($resource->fresh()->is_active);

        $this->actingAs($admin)
            ->delete(route('admin.resources.destroy', $resource))
            ->assertRedirect();

        $this->assertSoftDeleted($resource);
    }

    public function test_admin_management_pages_render(): void
    {
        $admin = $this->userWithRole(RoleEnum::ADMIN);

        $this->actingAs($admin);

        foreach ([
            'admin.locations',
            'admin.categories',
            'admin.facilities',
            'admin.settings',
            'admin.users',
            'admin.access',
            'admin.penalties',
            'admin.bookings',
            'admin.loans',
            'admin.reports',
            'admin.audit',
        ] as $route) {
            $this->get(route($route))->assertOk();
        }
    }

    public function test_bookings_page_shows_active_rooms_and_assets(): void
    {
        $admin = $this->userWithRole(RoleEnum::ADMIN);
        $borrower = User::factory()->create(['name' => 'Pemakai Aktif']);
        $location = $this->location();

        $room = Resource::create([
            'name' => 'Ruang Aktif',
            'type' => 'room',
            'location_id' => $location->id,
        ]);
        Booking::create([
            'user_id' => $borrower->id,
            'resource_id' => $room->id,
            'title' => 'Rapat Berjalan',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'status' => 'checked_in',
        ]);

        $assetResource = Resource::create([
            'name' => 'Kamera Aktif',
            'type' => 'asset',
            'location_id' => $location->id,
        ]);
        $asset = $assetResource->asset()->create(['code' => 'CAM-ACT', 'condition' => 'good']);
        $assetBooking = Booking::create([
            'user_id' => $borrower->id,
            'resource_id' => $assetResource->id,
            'title' => 'Pinjam Berjalan',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'status' => 'checked_in',
        ]);
        AssetLoan::create([
            'booking_id' => $assetBooking->id,
            'asset_id' => $asset->id,
            'user_id' => $borrower->id,
            'status' => 'in_use',
            'handed_over_at' => now()->subHour(),
            'due_at' => now()->addHour(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.bookings'))
            ->assertOk()
            ->assertSee('Ruangan sedang dipakai')
            ->assertSee('Ruang Aktif')
            ->assertSee('Kamera Aktif')
            ->assertSee('Pemakai Aktif');
    }

    public function test_non_admin_cannot_access_management_pages(): void
    {
        $manager = $this->userWithRole(RoleEnum::RESOURCE_MANAGER);

        $this->actingAs($manager)->get(route('admin.settings'))->assertForbidden();
        $this->actingAs($manager)->get(route('admin.users'))->assertForbidden();
        $this->actingAs($manager)->get(route('admin.loans'))->assertOk();
        $this->actingAs($manager)->get(route('admin.bookings'))->assertOk();
    }

    public function test_admin_can_manage_locations_settings_and_users(): void
    {
        $admin = $this->userWithRole(RoleEnum::ADMIN);

        $this->actingAs($admin)
            ->get(route('admin.locations'))
            ->assertOk()
            ->assertSee('data-location-map', false)
            ->assertSee('location-map.js', false);

        $this->actingAs($admin)
            ->post(route('admin.locations.store'), [
                'name' => 'Gedung Baru',
                'address' => 'Lantai 3',
                'geofence_type' => 'radius',
                'latitude' => -6.2,
                'longitude' => 106.8,
                'radius' => 150,
            ])
            ->assertSessionHasNoErrors();

        $location = Location::where('name', 'Gedung Baru')->firstOrFail();
        $this->assertSame(150, $location->radius);
        $this->assertNull($location->polygon);

        $this->actingAs($admin)
            ->post(route('admin.locations.store'), [
                'name' => 'Taman Polygon',
                'geofence_type' => 'polygon',
                'latitude' => -6.21,
                'longitude' => 106.81,
                'polygon' => json_encode([
                    ['lat' => -6.21, 'lng' => 106.81],
                    ['lat' => -6.211, 'lng' => 106.811],
                    ['lat' => -6.212, 'lng' => 106.812],
                ]),
            ])
            ->assertSessionHasNoErrors();

        $polygonLocation = Location::where('name', 'Taman Polygon')->firstOrFail();
        $this->assertCount(3, $polygonLocation->polygon);
        $this->assertSame(100, $polygonLocation->radius);

        $this->actingAs($admin)
            ->put(route('admin.settings.update'), [
                'settings' => [
                    'strike_threshold' => '5',
                    'face_verification_enabled' => '1',
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(5, Setting::get('strike_threshold'));
        $this->assertTrue(Setting::get('face_verification_enabled'));

        $memberRole = Role::firstOrCreate(['name' => RoleEnum::MEMBER]);

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'User Baru',
                'email' => 'baru@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role_id' => $memberRole->id,
            ])
            ->assertSessionHasNoErrors();

        $user = User::where('email', 'baru@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole(RoleEnum::MEMBER->value));
    }

    public function test_admin_can_handover_and_return_an_asset_loan(): void
    {
        $admin = $this->userWithRole(RoleEnum::ADMIN);
        $location = $this->location();
        $resource = Resource::create([
            'name' => 'Kamera Pinjam',
            'type' => 'asset',
            'location_id' => $location->id,
        ]);
        $asset = $resource->asset()->create(['code' => 'CAM-999', 'condition' => 'good']);
        $borrower = User::factory()->create();
        $booking = Booking::create([
            'user_id' => $borrower->id,
            'resource_id' => $resource->id,
            'title' => 'Pinjam kamera',
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHours(2),
            'status' => 'approved',
        ]);
        $loan = AssetLoan::create([
            'booking_id' => $booking->id,
            'asset_id' => $asset->id,
            'user_id' => $borrower->id,
            'status' => 'reserved',
            'due_at' => now()->addHours(2),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.loans.handover', $loan))
            ->assertSessionHasNoErrors();

        $this->assertSame('handed_over', $loan->fresh()->status->value);
        $this->assertSame(1, LoanEvent::where('asset_loan_id', $loan->id)->where('event_type', 'handover')->count());

        $this->actingAs($admin)
            ->post(route('admin.loans.return', $loan), [
                'condition' => 'good',
                'notes' => 'Kembali lengkap',
            ])
            ->assertSessionHasNoErrors();

        $loan->refresh();

        $this->assertSame('returned', $loan->status->value);
        $this->assertNotNull($loan->returned_at);
        $this->assertSame(1, ConditionReport::where('asset_loan_id', $loan->id)->count());
        $this->assertSame('good', $asset->fresh()->condition);
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

    private function booking(): Booking
    {
        $resource = Resource::create([
            'name' => 'Ruang Diskusi',
            'type' => 'room',
            'location_id' => $this->location()->id,
        ]);

        return Booking::create([
            'user_id' => User::factory()->create()->id,
            'resource_id' => $resource->id,
            'title' => 'Rapat Mingguan',
            'starts_at' => now()->setTime(10, 0),
            'ends_at' => now()->setTime(12, 0),
            'status' => 'pending',
        ]);
    }
}
