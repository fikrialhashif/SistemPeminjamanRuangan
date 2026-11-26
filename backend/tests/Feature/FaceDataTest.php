<?php

namespace Tests\Feature;

use App\Models\FaceEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FaceDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_delete_returns_503_and_keeps_the_record_when_the_face_service_fails(): void
    {
        Http::fake(['*/enroll/users/*' => Http::response('boom', 500)]);
        $user = $this->enrolledUser();

        $this->actingAs($user)
            ->deleteJson('/api/v1/face')
            ->assertStatus(503)
            ->assertJsonPath('code', 'face_service_unavailable');

        $this->assertNotNull($user->faceEnrollment()->first());
    }

    public function test_delete_returns_503_when_the_face_service_is_unreachable(): void
    {
        Http::fake(['*/enroll/users/*' => Http::failedConnection()]);
        $user = $this->enrolledUser();

        $this->actingAs($user)->deleteJson('/api/v1/face')->assertStatus(503);

        $this->assertNotNull($user->faceEnrollment()->first());
    }

    public function test_user_can_enroll_again_after_deleting_face_data(): void
    {
        Http::fake(['*' => Http::response(['deleted' => true, 'status' => 'success'])]);
        $user = User::factory()->create();
        $payload = ['consent' => true, 'images' => ['a', 'b', 'c']];
        $this->actingAs($user)->postJson('/api/v1/face/enroll', $payload)->assertCreated();
        $this->actingAs($user->fresh())->deleteJson('/api/v1/face')->assertOk();

        $this->actingAs($user->fresh())->postJson('/api/v1/face/enroll', $payload)->assertCreated();

        $this->assertSame('enrolled', $user->faceEnrollment()->sole()->status);
        $this->assertSame(1, FaceEnrollment::withTrashed()->count());
    }

    private function enrolledUser(): User
    {
        $user = User::factory()->create();
        FaceEnrollment::create([
            'user_id' => $user->id,
            'consent_given_at' => now(),
            'status' => 'enrolled',
            'samples_count' => 3,
        ]);

        return $user;
    }
}
