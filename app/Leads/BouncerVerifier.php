<?php

namespace App\Leads;

use Illuminate\Support\Facades\Http;

/**
 * Verifies an email with Bouncer (usebouncer.com). Maps Bouncer's result to our
 * status vocabulary. Any API trouble degrades to "unknown" rather than failing
 * the entry.
 */
class BouncerVerifier implements EmailVerifier
{
    public function __construct(protected string $apiKey) {}

    /**
     * @return array{status: string, score: ?int}
     */
    public function verify(string $email): array
    {
        try {
            $response = Http::withHeaders(['x-api-key' => $this->apiKey])
                ->timeout(15)
                ->get('https://api.usebouncer.com/v1.1/email/verify', ['email' => $email]);

            if (! $response->successful()) {
                return ['status' => self::UNKNOWN, 'score' => null];
            }

            $status = (string) $response->json('status', 'unknown');

            return [
                'status' => match ($status) {
                    'deliverable' => self::DELIVERABLE,
                    'risky' => self::RISKY,
                    'undeliverable' => self::UNDELIVERABLE,
                    default => self::UNKNOWN,
                },
                'score' => is_numeric($response->json('score')) ? (int) $response->json('score') : null,
            ];
        } catch (\Throwable) {
            return ['status' => self::UNKNOWN, 'score' => null];
        }
    }
}
