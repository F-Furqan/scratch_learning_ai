<?php

namespace App\Services\Payments;

use Illuminate\Support\Carbon;

class PaddleWebhookVerifier
{
    public function verify(string $rawPayload, ?string $signatureHeader, string $secret, int $toleranceSeconds = 5): bool
    {
        if (blank($signatureHeader) || blank($secret)) {
            return false;
        }

        $parts = $this->parseSignatureHeader((string) $signatureHeader);
        $timestamp = $parts['ts'] ?? null;
        $signatures = $parts['h1'] ?? [];

        if (! is_string($timestamp) || $timestamp === '' || $signatures === []) {
            return false;
        }

        if (! ctype_digit($timestamp)) {
            return false;
        }

        $webhookTimestamp = (int) $timestamp;
        $currentTimestamp = Carbon::now()->getTimestamp();

        if ($toleranceSeconds > 0 && abs($currentTimestamp - $webhookTimestamp) > $toleranceSeconds) {
            return false;
        }

        $signedPayload = $timestamp.':'.$rawPayload;
        $expected = hash_hmac('sha256', $signedPayload, $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{ts?: string, h1?: list<string>}
     */
    private function parseSignatureHeader(string $header): array
    {
        $parsed = [];

        foreach (preg_split('/[;,]/', $header) ?: [] as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);

            if (! is_string($key) || ! is_string($value) || $key === '') {
                continue;
            }

            if ($key === 'h1') {
                $parsed['h1'] ??= [];
                $parsed['h1'][] = $value;

                continue;
            }

            if ($key === 'ts') {
                $parsed['ts'] = $value;
            }
        }

        return $parsed;
    }
}
