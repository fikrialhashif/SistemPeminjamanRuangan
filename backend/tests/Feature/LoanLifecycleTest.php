<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\LoanStatus;
use App\Enums\Role as RoleEnum;
use App\Jobs\CompleteFinishedBookings;
use App\Jobs\ExpireNoShowBookings;
use App\Jobs\MarkOverdueLoans;
use App\Models\AssetLoan;
use App\Models\Booking;
use App\Models\Location;
use App\Models\Resource;
use App\Models\Role;
use App\Models\Strike;
use App\Models\User;
use App\Services\QrTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class LoanLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeTime();
        Http::preventStrayRequests();
    }

    public function test_handover_verifies_the_borrowers_face_and_checks_the_booking_in(): void
    {
        Http::fake(['*/verify' => Http::response(['match' => true])]);
        $loan = $this->loan();

        $this->actingAs($this->userWithRole(RoleEnum::RESOURCE_MANAGER))
            ->postJson("/api/v1/loans/{$loan->id}/handover", $this->handoverPayload($loan))
            ->assertOk();

        Http::assertSent(fn (Request $request): bool => $request['user_id'] === $loan->user_id);
        $this->assertSame(LoanStatus::HANDED_OVER, $loan->fresh()->status);
        $this->assertSame(BookingStatus::CHECKED_IN, $loan->booking->fresh()->status);
    }

    public function test_handover_returns_422_when_the_borrower_has_no_enrolled_face(): void
    {
        Http::fake(['*/verify' => Http::response(['detail' => 'User not enrolled'], 404)]);
        $loan = $this->loan();

        $this->actingAs($this->userWithRole(RoleEnum::RESOURCE_MANAGER))
            ->postJson("/api/v1/loans/{$loan->id}/handover", $this->handoverPayload($loan))
            ->assertStatus(422)
            ->assertJsonPath('reason', 'face')
            ->assertJsonPath('message', 'Peminjam belum mendaftarkan wajah.');

        $this->assertSame(LoanStatus::RESERVED, $loan->fresh()->status);
    }

    public function test_manager_override_hands_the_asset_over(): void
    {
        $loan = $this->loan();

        $this->actingAs($this->userWithRole(RoleEnum::RESOURCE_MANAGER))
            ->postJson("/api/v1/loans/{$loan->id}/override", ['reason' => 'Kamera ponsel rusak, identitas dicek manual'])
            ->assertOk();

        $this->assertSame(LoanStatus::HANDED_OVER, $loan->fresh()->status);
        $this->assertSame('in_use', $loan->asset->fresh()->status);
        $this->assertSame(BookingStatus::CHECKED_IN, $loan->booking->fresh()->status);
    }

    public function test_member_cannot_override(): void
    {
        $loan = $this->loan();

        $this->actingAs($this->userWithRole(RoleEnum::MEMBER))
            ->postJson("/api/v1/loans/{$loan->id}/override", ['reason' => 'Kamera ponsel rusak, identitas dicek manual'])
            ->assertForbidden();

        $this->assertSame(LoanStatus::RESERVED, $loan->fresh()->status);
    }

    public function test_handover_returns_422_when_the_booking_is_not_approved(): void
    {
        $loan = $this->loan(bookingStatus: BookingStatus::CANCELLED);

        $this->actingAs($this->userWithRole(RoleEnum::RESOURCE_MANAGER))
            ->postJson("/api/v1/loans/{$loan->id}/handover", $this->handoverPayload($loan))
            ->assertStatus(422);

        $this->assertSame(LoanStatus::RESERVED, $loan->fresh()->status);
    }

    public function test_override_returns_422_when_the_booking_is_not_approved(): void
    {
        $loan = $this->loan(bookingStatus: BookingStatus::CANCELLED);

        $this->actingAs($this->userWithRole(RoleEnum::RESOURCE_MANAGER))
            ->postJson("/api/v1/loans/{$loan->id}/override", ['reason' => 'Kamera ponsel rusak, identitas dicek manual'])
            ->assertStatus(422);

        $this->assertSame(LoanStatus::RESERVED, $loan->fresh()->status);
    }

    public function test_admin_panel_refuses_handover_when_the_booking_is_not_approved(): void
    {
        $loan = $this->loan(bookingStatus: BookingStatus::CANCELLED);

        $this->actingAs($this->userWithRole(RoleEnum::ADMIN))
            ->post(route('admin.loans.handover', $loan))
            ->assertSessionHasErrors('loan');

        $this->assertSame(LoanStatus::RESERVED, $loan->fresh()->status);
    }

    public function test_cancelling_a_booking_closes_its_reserved_loan(): void
    {
        $loan = $this->loan(startsInMinutes: 180);

        $this->actingAs($loan->user)
            ->deleteJson("/api/v1/bookings/{$loan->booking_id}")
            ->assertOk();

        $this->assertSame(LoanStatus::CLOSED, $loan->fresh()->status);
    }

    public function test_no_show_job_ignores_an_asset_that_was_handed_over(): void
    {
        // Bookings handed over before this fix are still "approved".
        $loan = $this->loan(startsInMinutes: -30, loanStatus: LoanStatus::HANDED_OVER);

        app()->call([new ExpireNoShowBookings, 'handle']);

        $this->assertSame(BookingStatus::APPROVED, $loan->booking->fresh()->status);
        $this->assertSame(0, Strike::count());
    }

    public function test_no_show_job_closes_the_loan_of_an_uncollected_asset(): void
    {
        $loan = $this->loan(startsInMinutes: -30);

        app()->call([new ExpireNoShowBookings, 'handle']);

        $this->assertSame(BookingStatus::NO_SHOW, $loan->booking->fresh()->status);
        $this->assertSame(LoanStatus::CLOSED, $loan->fresh()->status);
        $this->assertSame(1, Strike::count());
    }

    #[TestWith([LoanStatus::HANDED_OVER])]
    #[TestWith([LoanStatus::IN_USE])]
    public function test_overdue_loan_gets_exactly_one_strike(LoanStatus $status): void
    {
        $loan = $this->loan(startsInMinutes: -300, loanStatus: $status);

        app()->call([new MarkOverdueLoans, 'handle']);
        app()->call([new MarkOverdueLoans, 'handle']);

        $this->assertSame(1, Strike::where('user_id', $loan->user_id)->count());
    }

    #[TestWith(['good', 'available'])]
    #[TestWith(['damaged', 'damaged'])]
    #[TestWith(['lost', 'lost'])]
    public function test_return_sets_asset_status_from_condition_and_completes_booking(string $condition, string $assetStatus): void
    {
        $loan = $this->loan(bookingStatus: BookingStatus::CHECKED_IN, loanStatus: LoanStatus::HANDED_OVER);

        $this->actingAs($this->userWithRole(RoleEnum::RESOURCE_MANAGER))
            ->postJson("/api/v1/loans/{$loan->id}/return", ['condition' => $condition])
            ->assertOk();

        $this->assertSame($assetStatus, $loan->asset->fresh()->status);
        $this->assertSame(BookingStatus::COMPLETED, $loan->booking->fresh()->status);
    }

    #[TestWith(['lost'])]
    #[TestWith(['maintenance'])]
    public function test_admin_panel_return_sets_asset_status_and_completes_booking(string $condition): void
    {
        $loan = $this->loan(bookingStatus: BookingStatus::CHECKED_IN, loanStatus: LoanStatus::HANDED_OVER);

        $this->actingAs($this->userWithRole(RoleEnum::ADMIN))
            ->post(route('admin.loans.return', $loan), ['condition' => $condition])
            ->assertSessionHasNoErrors();

        $this->assertSame($condition, $loan->asset->fresh()->status);
        $this->assertSame(BookingStatus::COMPLETED, $loan->booking->fresh()->status);
    }

    public function test_finished_checked_in_bookings_are_completed(): void
    {
        $finished = $this->loan(startsInMinutes: -300, bookingStatus: BookingStatus::CHECKED_IN, loanStatus: LoanStatus::RETURNED)->booking;
        $ongoing = $this->loan(startsInMinutes: -10, bookingStatus: BookingStatus::CHECKED_IN, loanStatus: LoanStatus::HANDED_OVER)->booking;
        $unreturned = $this->loan(startsInMinutes: -300, bookingStatus: BookingStatus::CHECKED_IN, loanStatus: LoanStatus::HANDED_OVER)->booking;

        app()->call([new CompleteFinishedBookings, 'handle']);

        $this->assertSame(BookingStatus::COMPLETED, $finished->fresh()->status);
        $this->assertSame(BookingStatus::CHECKED_IN, $ongoing->fresh()->status);
        $this->assertSame(BookingStatus::CHECKED_IN, $unreturned->fresh()->status);
    }

    #[TestWith(['return'])]
    #[TestWith(['damage'])]
    public function test_condition_photos_are_stored_on_the_private_disk(string $action): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $loan = $this->loan(bookingStatus: BookingStatus::CHECKED_IN, loanStatus: LoanStatus::HANDED_OVER);

        $this->actingAs($this->userWithRole(RoleEnum::RESOURCE_MANAGER))
            ->post("/api/v1/loans/{$loan->id}/{$action}", [
                'condition' => 'damaged',
                'notes' => 'Lensa retak',
                'photo' => UploadedFile::fake()->create('kondisi.jpg', 10, 'image/jpeg'),
            ], ['Accept' => 'application/json'])
            ->assertSuccessful();

        Storage::disk('local')->assertExists($loan->conditionReports()->sole()->photo_path);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    /**
     * A one hour asset booking with its loan.
     */
    private function loan(
        int $startsInMinutes = 5,
        BookingStatus $bookingStatus = BookingStatus::APPROVED,
        LoanStatus $loanStatus = LoanStatus::RESERVED,
    ): AssetLoan {
        $location = Location::create(['name' => 'Gedung A', 'latitude' => -6.2, 'longitude' => 106.8]);
        $resource = Resource::create(['name' => 'Kamera DSLR', 'type' => 'asset', 'location_id' => $location->id, 'is_active' => true]);
        $asset = $resource->asset()->create(['code' => fake()->unique()->bothify('CAM-###'), 'condition' => 'good']);
        $borrower = User::factory()->create();
        $booking = Booking::create([
            'user_id' => $borrower->id,
            'resource_id' => $resource->id,
            'starts_at' => now()->addMinutes($startsInMinutes),
            'ends_at' => now()->addMinutes($startsInMinutes + 60),
            'status' => $bookingStatus,
        ]);

        return AssetLoan::create([
            'booking_id' => $booking->id,
            'asset_id' => $asset->id,
            'user_id' => $borrower->id,
            'status' => $loanStatus,
            'due_at' => $booking->ends_at,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function handoverPayload(AssetLoan $loan): array
    {
        return [
            'asset_token' => app(QrTokenService::class)->generateForAsset($loan->asset)['token'],
            'face_image' => 'face',
            'gps_latitude' => -6.2,
            'gps_longitude' => 106.8,
            'gps_accuracy' => 5,
            'gps_timestamp' => now()->toIso8601String(),
            'gps_is_mock' => false,
        ];
    }

    private function userWithRole(RoleEnum $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => $role]));

        return $user;
    }
}
