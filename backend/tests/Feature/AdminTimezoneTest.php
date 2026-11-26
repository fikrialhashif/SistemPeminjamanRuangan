<?php

namespace Tests\Feature;

use App\Enums\Role as RoleEnum;
use App\Models\AssetLoan;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Location;
use App\Models\Resource;
use App\Models\Role;
use App\Models\Suspension;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Times are stored in UTC; the admin panel reads and writes them in WIB (UTC+7).
 */
class AdminTimezoneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 00:30 WIB on 1 December
        $this->travelTo(Carbon::parse('2026-11-30 17:30:00', 'UTC'));
    }

    public function test_approval_list_shows_booking_times_in_wib(): void
    {
        $booking = $this->booking('pending');
        $booking->approvals()->create(['level' => 1, 'status' => 'pending', 'expires_at' => '2026-12-03 05:15:00']);

        $this->actingAs($this->admin())
            ->get(route('admin.approvals'))
            ->assertOk()
            ->assertSee('09:00&ndash;10:00', false)
            ->assertSee('12:15');
    }

    public function test_loan_list_shows_due_time_in_wib(): void
    {
        $booking = $this->booking('approved', 'asset');
        $asset = $booking->resource->asset()->create(['code' => 'CAM-001']);
        AssetLoan::create([
            'booking_id' => $booking->id,
            'asset_id' => $asset->id,
            'user_id' => $booking->user_id,
            'status' => 'reserved',
            'due_at' => '2026-12-01 03:00:00',
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.loans'))
            ->assertOk()
            ->assertSee('10:00');
    }

    public function test_dashboard_shows_the_wib_date(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('1 Desember 2026');
    }

    public function test_bookings_board_queues_todays_wib_bookings(): void
    {
        $this->booking('approved');

        $response = $this->actingAs($this->admin())->get(route('admin.bookings'))->assertOk()->assertSee('09:00');

        $this->assertCount(1, $response->viewData('queuedEntries'));
    }

    public function test_suspension_entered_in_wib_is_stored_in_utc(): void
    {
        $target = User::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.suspensions.store'), [
                'user_id' => $target->id,
                'starts_at' => '2026-12-01T00:15',
                'ends_at' => '2026-12-08T00:15',
            ])
            ->assertSessionHasNoErrors();

        $suspension = Suspension::sole();
        $this->assertSame('2026-11-30 17:15:00', $suspension->starts_at->toDateTimeString());
        $this->assertSame('2026-12-07 17:15:00', $suspension->ends_at->toDateTimeString());
        $this->assertTrue($target->isSuspended());
    }

    public function test_suspension_form_defaults_to_the_current_wib_time(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.penalties'))
            ->assertOk()
            ->assertSee('value="2026-12-01T00:30"', false);
    }

    public function test_report_range_covers_whole_wib_days(): void
    {
        // 01:00 WIB on 1 December
        $this->booking('approved', startsAt: '2026-11-30 18:00:00');

        $response = $this->actingAs($this->admin())
            ->get(route('admin.reports', ['from' => '2026-12-01', 'to' => '2026-12-01']))
            ->assertOk();

        $this->assertSame(1, $response->viewData('totalBookings'));
        $this->assertSame('2026-12-01', $response->viewData('fromInput'));
        $this->assertSame('2026-12-01', $response->viewData('toInput'));
    }

    public function test_audit_date_filter_covers_whole_wib_days(): void
    {
        $admin = $this->admin();
        AuditLog::query()->toBase()->delete();
        AuditLog::create(['action' => 'created', 'auditable_type' => User::class, 'auditable_id' => $admin->id]);

        $response = $this->actingAs($admin)
            ->get(route('admin.audit', ['from' => '2026-12-01', 'to' => '2026-12-01']))
            ->assertOk()
            ->assertSee('00:30:00');

        $this->assertSame(1, $response->viewData('logs')->total());
    }

    private function booking(string $status, string $type = 'room', string $startsAt = '2026-12-01 02:00:00'): Booking
    {
        $location = Location::create(['name' => 'Gedung A', 'latitude' => -6.2, 'longitude' => 106.8]);
        $resource = Resource::create(['name' => 'Ruang Rapat', 'type' => $type, 'location_id' => $location->id]);

        return Booking::create([
            'user_id' => User::factory()->create()->id,
            'resource_id' => $resource->id,
            'starts_at' => $startsAt,
            'ends_at' => Carbon::parse($startsAt)->addHour(),
            'status' => $status,
        ]);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => RoleEnum::ADMIN]));

        return $user;
    }
}
