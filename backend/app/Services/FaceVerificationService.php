<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class FaceVerificationService
{
    private function client(): PendingRequest
    {
        return Http::baseUrl(config('services.face.url'))
            ->withHeaders(['X-Service-Secret' => config('services.face.secret')])
            ->timeout(15);
    }

    public function enroll(User $user, array $images, ?array $poses = null): array
    {
        $payload = [
            'user_id' => $user->id,
            'images' => $images,
        ];

        if ($poses !== null) {
            $payload['poses'] = $poses;
        }

        $response = $this->client()->post('/enroll', $payload);

        return $response->throw()->json();
    }

    public function verify(User $user, string $image, ?string $challengeType = null, ?array $challengeResponse = null): array
    {
        $payload = [
            'user_id' => $user->id,
            'image' => $image,
        ];

        if ($challengeType) {
            $payload['challenge_type'] = $challengeType;
        }

        if ($challengeResponse) {
            $payload['challenge_response'] = $challengeResponse;
        }

        $response = $this->client()->post('/verify', $payload);

        return $response->throw()->json();
    }

    public function deleteUserData(User $user): bool
    {
        $response = $this->client()->delete("/enroll/users/{$user->id}");

        return $response->successful();
    }

    public function challenge(): array
    {
        try {
            $response = $this->client()->get('/verify/challenge');

            if ($response->successful()) {
                $data = $response->json();

                if (is_array($data) && ($data['challenge_type'] ?? null) === 'turn') {
                    return [
                        'challenge_type' => 'turn',
                        'instruction' => $this->challengeInstruction('turn'),
                        'issued_at' => now()->toIso8601String(),
                    ];
                }
            }
        } catch (\Throwable) {
            // Fall back to a locally issued challenge when the service is unreachable.
        }

        return $this->getChallenge();
    }

    public function getChallenge(): array
    {
        $challenge = 'turn';

        return [
            'challenge_type' => $challenge,
            'instruction' => $this->challengeInstruction($challenge),
            'issued_at' => now()->toIso8601String(),
        ];
    }

    public function challengeInstruction(string $challenge): string
    {
        return match ($challenge) {
            'turn' => 'Tolehkan kepala ke kiri atau kanan',
            'turn_left' => 'Tengokkan kepala ke kiri',
            'turn_right' => 'Tengokkan kepala ke kanan',
            default => 'Lakukan gerakan yang diminta',
        };
    }
}
