<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\Role as RoleEnum;
use App\Models\Approval;
use App\Models\Asset;
use App\Models\AssetLoan;
use App\Models\Booking;
use App\Models\Location;
use App\Models\Resource;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardChartsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_system_charts(): void
    {
        $room = $this->room('Ruang Rapat');
        $this->booking($room, BookingStatus::COMPLETED, now()->subDays(2));

        $this->actingAs($this->userWithRole(RoleEnum::ADMIN))
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Tren peminjaman')
            ->assertSee('Distribusi status peminjaman')
            ->assertSee('Ruangan paling sering dipakai')
            ->assertSee('Ruang Rapat')
            ->assertDontSee('Antrian persetujuan per ruangan')
            ->assertDontSee('Kondisi aset');
    }

    public function test_approver_sees_approval_charts_only(): void
    {
        $room = $this->room('Ruang Persetujuan');
        $booking = $this->booking($room, BookingStatus::PENDING, now()->subDay());
        Approval::create(['booking_id' => $booking->id, 'level' => 1, 'status' => 'pending']);

        $this->actingAs($this->userWithRole(RoleEnum::APPROVER))
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Pengajuan persetujuan')
            ->assertSee('Hasil keputusan persetujuan')
            ->assertSee('Antrian persetujuan per ruangan')
            ->assertSee('Ruang Persetujuan')
            ->assertDontSee('Tren peminjaman')
            ->assertDontSee('Kondisi aset');
    }

    public function test_resource_manager_sees_room_and_asset_charts_with_data(): void
    {
        $manager = $this->userWithRole(RoleEnum::RESOURCE_MANAGER);
        $room = $this->room('Ruang Ukur');
        $booking = $this->booking($room, BookingStatus::COMPLETED, now()->subDays(3));
        $asset = Asset::create([
            'resource_id' => $room->id,
            'code' => 'AST-137',
            'condition' => 'damaged',
        ]);
        AssetLoan::create([
            'booking_id' => $booking->id,
            'asset_id' => $asset->id,
            'user_id' => $booking->user_id,
            'status' => 'reserved',
            'due_at' => now()->addDay(),
        ]);

        $this->actingAs($manager)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Pemakaian ruangan')
            ->assertSee('Aset paling sering dipinjam')
            ->assertSee('Kondisi aset')
            ->assertSee('120', false)
            ->assertDontSee('Pengajuan persetujuan')
            ->assertDontSee('id="period_asset_condition"', false);
    }

    public function test_each_chart_has_its_own_period_filter(): void
    {
        $admin = $this->userWithRole(RoleEnum::ADMIN);
        $room = $this->room('Ruang Filter');
        $this->booking($room, BookingStatus::COMPLETED, now()->subDays(45));
        $this->booking($room, BookingStatus::COMPLETED, now()->subDays(5));

        $this->actingAs($admin)
            ->get(route('admin.dashboard', ['periods' => ['booking_status' => 1]]))
            ->assertOk()
            ->assertSee('id="period_booking_status"', false)
            ->assertSee('value="1" selected', false)
            ->assertSee('1 bulan terakhir')
            ->assertSee('6 bulan terakhir')
            ->assertSee('<span class="chart-donut-total">1</span>', false);

        $this->actingAs($admin)
            ->get(route('admin.dashboard', ['periods' => ['booking_status' => 2, 'booking_trend' => 4]]))
            ->assertOk()
            ->assertSee('2 bulan terakhir')
            ->assertSee('4 bulan terakhir')
            ->assertSee('<span class="chart-donut-total">2</span>', false)
            ->assertSee('name="periods[booking_trend]" value="4"', false);

        $this->actingAs($admin)
            ->get(route('admin.dashboard', ['periods' => ['booking_status' => 99]]))
            ->assertOk()
            ->assertSee('6 bulan terakhir')
            ->assertSee('value="6" selected', false)
            ->assertSee('<span class="chart-donut-total">2</span>', false);
    }

    public function test_revoked_permission_hides_the_related_charts(): void
    {
        $role = Role::firstOrCreate(['name' => RoleEnum::APPROVER]);
        $role->update(['permissions' => []]);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('Pengajuan persetujuan')
            ->assertDontSee('Hasil keputusan persetujuan');
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
            'name' => 'Gedung Uji',
            'latitude' => -6.2,
            'longitude' => 106.8,
        ]);
    }

    private function room(string $name): Resource
    {
        return Resource::create([
            'name' => $name,
            'type' => 'room',
            'location_id' => $this->location()->id,
        ]);
    }

    private function booking(Resource $resource, BookingStatus $status, Carbon $startsAt): Booking
    {
        return Booking::create([
            'user_id' => User::factory()->create()->id,
            'resource_id' => $resource->id,
            'title' => 'Peminjaman uji',
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(2),
            'status' => $status,
        ]);
    }
}
