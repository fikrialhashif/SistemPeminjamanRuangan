<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\CheckInResult;
use App\Enums\Role;
use App\Models\Approval;
use App\Models\Asset;
use App\Models\Booking;
use App\Models\CheckInAttempt;
use App\Models\FaceEnrollment;
use App\Models\Location;
use App\Models\Resource;
use App\Models\Role as RoleModel;
use App\Models\Setting;
use App\Models\User;
use App\Services\ApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class BookingApprovalAndDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_waits_for_approver_even_without_configured_approval_path(): void
    {
        [$user, $room] = $this->userWithEnrolledFaceAndRoom();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/bookings', $this->payload($room))
            ->assertCreated();

        $booking = Booking::findOrFail($response->json('id'));

        $this->assertSame(BookingStatus::PENDING, $booking->status);
        $this->assertCount(1, $booking->approvals);
        $this->assertSame('pending', $booking->approvals->first()->status);
    }

    public function test_approving_asset_booking_creates_reserved_asset_loan(): void
    {
        $user = User::factory()->create();
        $approver = User::factory()->create();
        $resource = Resource::create([
            'name' => 'Kamera DSLR-01',
            'type' => 'asset',
            'is_active' => true,
        ]);
        Asset::create([
            'resource_id' => $resource->id,
            'code' => 'CAM-001',
            'condition' => 'good',
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/bookings', $this->payload($resource))
            ->assertCreated();

        $booking = Booking::findOrFail($response->json('id'));
        app(ApprovalService::class)->approve(
            Approval::query()->where('booking_id', $booking->id)->firstOrFail(),
            $approver,
        );

        $this->assertSame(BookingStatus::APPROVED, $booking->fresh()->status);
        $this->assertSame(1, $booking->assetLoan()->count());

        $loan = $booking->assetLoan()->firstOrFail();
        $this->assertSame('reserved', $loan->status->value);
        $this->assertSame('CAM-001', $loan->asset->code);
        $this->assertSame($user->id, $loan->user_id);
        $this->assertNotNull($loan->due_at);
    }

    public function test_check_in_is_rejected_before_approver_approval(): void
    {
        [$user, $room] = $this->userWithEnrolledFaceAndRoom();
        $booking = $this->createBooking($user, $room);

        $this->actingAs($user)
            ->postJson("/api/v1/bookings/{$booking->id}/check-in", $this->evidence())
            ->assertStatus(422)
            ->assertJsonPath('errors.status.0', 'Booking belum disetujui oleh approver.');
    }

    public function test_detail_contains_resource_location_and_check_in_history(): void
    {
        [$user, $room] = $this->userWithEnrolledFaceAndRoom();
        $booking = $this->createBooking($user, $room);

        CheckInAttempt::create([
            'booking_id' => $booking->id,
            'user_id' => $user->id,
            'result' => CheckInResult::ACCEPTED,
            'qr_valid' => true,
            'gps_valid' => true,
            'face_valid' => true,
            'gps_latitude' => 0.5677159,
            'gps_longitude' => 101.4227797,
            'gps_accuracy' => 13.44,
            'gps_timestamp' => now(),
            'face_score' => 0.8599,
        ]);

        $this->actingAs($user)
            ->getJson("/api/v1/bookings/{$booking->id}")
            ->assertOk()
            ->assertJsonPath('resource.location.name', 'Gedung Utama')
            ->assertJsonPath('check_in_attempts.0.result', 'accepted')
            ->assertJsonPath('check_in_attempts.0.qr_valid', true);
    }

    public function test_detail_returns_403_for_another_members_booking(): void
    {
        [$owner, $room] = $this->userWithEnrolledFaceAndRoom();
        $booking = $this->createBooking($owner, $room);

        $this->actingAs(User::factory()->create())
            ->getJson("/api/v1/bookings/{$booking->id}")
            ->assertForbidden();
    }

    public function test_admin_can_view_another_users_booking(): void
    {
        [$owner, $room] = $this->userWithEnrolledFaceAndRoom();
        $booking = $this->createBooking($owner, $room);

        $this->actingAs($this->userWithRole(Role::ADMIN))
            ->getJson("/api/v1/bookings/{$booking->id}")
            ->assertOk()
            ->assertJsonPath('id', $booking->id);
    }

    #[TestWith(['approve', BookingStatus::CANCELLED])]
    #[TestWith(['reject', BookingStatus::CANCELLED])]
    #[TestWith(['approve', BookingStatus::EXPIRED])]
    public function test_decision_returns_422_when_booking_is_no_longer_pending(string $decision, BookingStatus $status): void
    {
        [$owner, $room] = $this->userWithEnrolledFaceAndRoom();
        $booking = $this->createBooking($owner, $room);
        $booking->update(['status' => $status]);
        $approval = $booking->approvals()->firstOrFail();

        $this->actingAs($this->userWithRole(Role::APPROVER))
            ->postJson("/api/v1/approvals/{$approval->id}/{$decision}")
            ->assertStatus(422)
            ->assertJsonPath('errors.approval.0', 'Pemesanan ini sudah tidak menunggu persetujuan.');

        $this->assertSame($status, $booking->fresh()->status);
        $this->assertSame('pending', $approval->fresh()->status);
    }

    public function test_pending_list_omits_approvals_of_cancelled_bookings(): void
    {
        [$owner, $room] = $this->userWithEnrolledFaceAndRoom();
        $booking = $this->createBooking($owner, $room);
        $this->actingAs($owner)->deleteJson("/api/v1/bookings/{$booking->id}")->assertOk();

        $this->actingAs($this->userWithRole(Role::APPROVER))
            ->getJson('/api/v1/approvals/pending')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    private function userWithRole(Role $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(RoleModel::firstOrCreate(['name' => $role]));

        return $user;
    }

    /**
     * @return array{0: User, 1: resource}
     */
    private function userWithEnrolledFaceAndRoom(): array
    {
        Setting::set('face_verification_enabled', true, 'boolean');

        $user = User::factory()->create();
        FaceEnrollment::create([
            'user_id' => $user->id,
            'consent_given_at' => now(),
            'status' => 'enrolled',
            'samples_count' => 3,
        ]);

        $location = Location::create([
            'name' => 'Gedung Utama',
            'latitude' => 0.5677159,
            'longitude' => 101.4227797,
        ]);
        $room = Resource::create([
            'name' => 'Ruang Rapat A1',
            'type' => 'room',
            'location_id' => $location->id,
            'is_active' => true,
        ]);

        return [$user, $room];
    }

    private function createBooking(User $user, Resource $room): Booking
    {
        $response = $this->actingAs($user)
            ->postJson('/api/v1/bookings', $this->payload($room))
            ->assertCreated();

        return Booking::findOrFail($response->json('id'));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Resource $room): array
    {
        return [
            'resource_id' => $room->id,
            'starts_at' => now()->addMinutes(5)->toIso8601String(),
            'ends_at' => now()->addMinutes(65)->toIso8601String(),
            'title' => 'Rapat tim',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function evidence(): array
    {
        return [
            'qr_token' => 'test-token',
            'gps_latitude' => 0.5677159,
            'gps_longitude' => 101.4227797,
            'gps_accuracy' => 13.44,
            'gps_timestamp' => now()->toIso8601String(),
            'gps_is_mock' => false,
        ];
    }
}
