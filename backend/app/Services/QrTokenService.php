<?php

namespace App\Services;

use App\Models\Asset;

class QrTokenService
{
    private const ROOM_KIND = 'room';

    private const ASSET_KIND = 'asset';

    /**
     * @return array{token: string}
     */
    public function generate(int $resourceId): array
    {
        return ['token' => $this->sign([
            'kind' => self::ROOM_KIND,
            'resource_id' => $resourceId,
        ])];
    }

    /**
     * @return array{token: string}
     */
    public function generateForAsset(Asset $asset): array
    {
        return ['token' => $this->sign([
            'kind' => self::ASSET_KIND,
            'code' => $asset->code,
        ])];
    }

    /**
     * @return array{valid: bool, reason: string|null}
     */
    public function validate(string $token, int $resourceId, int $bookingId): array
    {
        $payload = $this->verify($token);

        if ($payload === null) {
            return ['valid' => false, 'reason' => 'Invalid token signature'];
        }

        if (! isset($payload['kind'])) {
            return ['valid' => false, 'reason' => 'Malformed token payload'];
        }

        if ($payload['kind'] !== self::ROOM_KIND || ! isset($payload['resource_id']) || (int) $payload['resource_id'] !== $resourceId) {
            return ['valid' => false, 'reason' => 'Resource mismatch'];
        }

        return ['valid' => true, 'reason' => null];
    }

    /**
     * @return array{valid: bool, reason: string|null}
     */
    public function validateForAsset(string $token, string $assetCode): array
    {
        $payload = $this->verify($token);

        if ($payload === null) {
            return ['valid' => false, 'reason' => 'Invalid token signature'];
        }

        if (! isset($payload['kind'])) {
            return ['valid' => false, 'reason' => 'Malformed token payload'];
        }

        if ($payload['kind'] !== self::ASSET_KIND || ! isset($payload['code']) || ! hash_equals((string) $payload['code'], $assetCode)) {
            return ['valid' => false, 'reason' => 'Asset mismatch'];
        }

        return ['valid' => true, 'reason' => null];
    }

    /**
     * @param  array<string, int|string>  $payload
     */
    private function sign(array $payload): string
    {
        $encoded = rtrim(strtr(base64_encode(json_encode($payload, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');

        return $encoded.'.'.$this->signature($encoded);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function verify(string $token): ?array
    {
        $parts = explode('.', $token, 2);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return null;
        }

        [$encoded, $signature] = $parts;

        if (! hash_equals($this->signature($encoded), $signature)) {
            return null;
        }

        $payload = base64_decode(strtr($encoded, '-_', '+/'), true);

        if ($payload === false) {
            return null;
        }

        $data = json_decode($payload, true);

        return is_array($data) ? $data : null;
    }

    private function signature(string $encoded): string
    {
        return hash_hmac('sha256', $encoded, (string) config('app.key'));
    }
}
