<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;

class QrTokenService
{
    public function generate(int $engagementId): array
    {
        $expiresAt = now()->addSeconds(600);
        $token = Crypt::encryptString(json_encode([
            'engagement_id' => $engagementId,
            'expires_at' => $expiresAt->toISOString(),
        ]));

        return ['token' => $token, 'expires_at' => $expiresAt->toISOString()];
    }

    public function validate(string $token, int $expectedEngagementId): ?int
    {
        try {
            $payload = json_decode(Crypt::decryptString($token), true);
        } catch (\Throwable) {
            return null;
        }
        if (
            ! isset($payload['engagement_id'], $payload['expires_at'])
            || now()->gt($payload['expires_at'])
            || (int) $payload['engagement_id'] !== $expectedEngagementId
        ) {
            return null;
        }

        return (int) $payload['engagement_id'];
    }
}
