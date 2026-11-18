<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\FaceVerificationService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FaceVerificationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.face.url' => 'http://face-service:8001',
            'services.face.secret' => 'test-secret',
        ]);
    }

    public function test_it_sends_the_service_secret_header_to_the_face_service(): void
    {
        Http::fake([
            'http://face-service:8001/verify' => Http::response([
                'match' => true,
                'score' => 0.9,
                'liveness_passed' => true,
                'reason' => 'Match found',
            ]),
        ]);

        $user = new User;
        $user->id = 7;

        app(FaceVerificationService::class)->verify($user, 'base64-image');

        Http::assertSent(function ($request) {
            return $request->url() === 'http://face-service:8001/verify'
                && $request->hasHeader('X-Service-Secret', 'test-secret');
        });
    }

    public function test_it_forwards_the_server_issued_liveness_challenge(): void
    {
        Http::fake([
            'http://face-service:8001/verify' => Http::response([
                'match' => true,
                'score' => 0.9,
                'liveness_passed' => true,
                'reason' => 'Match found',
            ]),
        ]);

        $user = new User;
        $user->id = 7;

        app(FaceVerificationService::class)->verify(
            $user,
            'base64-image',
            'turn_left',
            ['action' => 'turn_left'],
        );

        Http::assertSent(function ($request) {
            return $request->url() === 'http://face-service:8001/verify'
                && $request['challenge_type'] === 'turn_left'
                && $request['challenge_response'] === ['action' => 'turn_left'];
        });
    }

    public function test_it_requests_a_challenge_from_the_face_service(): void
    {
        Http::fake([
            'http://face-service:8001/verify/challenge' => Http::response([
                'challenge_type' => 'turn',
                'instruction' => 'Tolehkan kepala ke kiri atau kanan',
            ]),
        ]);

        $challenge = app(FaceVerificationService::class)->challenge();

        $this->assertSame('turn', $challenge['challenge_type']);
        $this->assertArrayHasKey('issued_at', $challenge);
    }

    public function test_it_deletes_face_data_through_the_enrollment_route(): void
    {
        Http::fake([
            'http://face-service:8001/enroll/users/7' => Http::response(['deleted' => true]),
        ]);

        $user = new User;
        $user->id = 7;

        $this->assertTrue(app(FaceVerificationService::class)->deleteUserData($user));

        Http::assertSent(fn ($request) => $request->url() === 'http://face-service:8001/enroll/users/7');
    }
}
