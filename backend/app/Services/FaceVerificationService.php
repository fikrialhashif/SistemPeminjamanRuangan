<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class FaceVerificationService
{
    private function client(): PendingRequest
    {
        return Http::baseUrl(config('services.face.url'))
            ->withHeaders(['X-Api-Key' => config('services.face.secret')])
            ->timeout(15);
    }

    public function enroll(User $user, array $images): array
    {
        $response = $this->client()->post('/enroll', [
            'user_id' => $user->id,
            'images' => $images,
        ]);

        return $response->throw()->json();
    }

    public function verify(User $user, string $image, ?array $challengeResponse = null): array
    {
        $payload = [
            'user_id' => $user->id,
            'image' => $image,
        ];

        if ($challengeResponse) {
            $payload['challenge_response'] = $challengeResponse;
        }

        $response = $this->client()->post('/verify', $payload);

        return $response->throw()->json();
    }

    public function deleteUserData(User $user): bool
    {
        $response = $this->client()->delete("/users/{$user->id}");

        return $response->successful();
    }

    public function getChallenge(): array
    {
        $challenges = ['blink', 'turn_left', 'turn_right'];

        return [
            'action' => Arr::random($challenges),
            'issued_at' => now()->toIso8601String(),
        ];
    }
}
