<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\FaceVerificationService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FaceVerificationServiceTest extends TestCase
{
    public function test_it_sends_the_service_secret_header_to_the_face_service(): void
    {
        config([
            'services.face.url' => 'http://face-service:8001',
            'services.face.secret' => 'test-secret',
        ]);

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
}
