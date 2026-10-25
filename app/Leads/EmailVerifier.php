<?php

namespace App\Leads;

/**
 * Checks whether a lead's email is deliverable. Bound to BouncerVerifier when a
 * Bouncer API key is configured, otherwise NullVerifier. The result is a status
 * — deliverable, risky, undeliverable or unknown — and an optional 0–100 score.
 */
interface EmailVerifier
{
    public const string DELIVERABLE = 'deliverable';

    public const string RISKY = 'risky';

    public const string UNDELIVERABLE = 'undeliverable';

    public const string UNKNOWN = 'unknown';

    public const string UNVERIFIED = 'unverified';

    /**
     * @return array{status: string, score: ?int}
     */
    public function verify(string $email): array;
}
