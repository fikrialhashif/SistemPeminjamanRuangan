<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class QrTokenService
{
    public function generate(int $resourceId): array
    {
        $ttl = Setting::get('qr_token_ttl_seconds', 30);
        $nonce = Str::random(32);
        $issuedAt = now()->timestamp;

        $payload = json_encode([
            'resource_id' => $resourceId,
            'issued_at' => $issuedAt,
            'nonce' => $nonce,
        ]);

        $token = encrypt($payload);
        $expiresAt = now()->addSeconds($ttl);

        return [
            'token' => $token,
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    public function validate(string $token, int $resourceId, int $bookingId): array
    {
        try {
            $payload = json_decode(decrypt($token), true);
        } catch (\Exception) {
            return ['valid' => false, 'reason' => 'Invalid token signature'];
        }

        if (! isset($payload['resource_id'], $payload['issued_at'], $payload['nonce'])) {
            return ['valid' => false, 'reason' => 'Malformed token payload'];
        }

        if ($payload['resource_id'] !== $resourceId) {
            return ['valid' => false, 'reason' => 'Resource mismatch'];
        }

        $ttl = Setting::get('qr_token_ttl_seconds', 30);
        $age = now()->timestamp - $payload['issued_at'];
        if ($age > $ttl) {
            return ['valid' => false, 'reason' => 'Token expired'];
        }

        $cacheKey = "qr_used:{$payload['nonce']}";
        if (Cache::has($cacheKey)) {
            return ['valid' => false, 'reason' => 'Token already used'];
        }

        Cache::put($cacheKey, $bookingId, $ttl * 2);

        return ['valid' => true, 'reason' => null];
    }
}
