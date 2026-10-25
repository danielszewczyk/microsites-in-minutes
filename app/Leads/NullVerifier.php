<?php

namespace App\Leads;

/**
 * Used when no Bouncer key is configured: nothing is checked and the entry is
 * marked "unverified".
 */
class NullVerifier implements EmailVerifier
{
    /**
     * @return array{status: string, score: ?int}
     */
    public function verify(string $email): array
    {
        return ['status' => self::UNVERIFIED, 'score' => null];
    }
}
