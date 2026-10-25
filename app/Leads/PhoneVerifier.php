<?php

namespace App\Leads;

/**
 * Checks whether a lead's phone number is valid and reachable (a number-lookup,
 * not an OTP challenge). Bound to a real lookup provider (e.g. Twilio Lookup)
 * when configured, otherwise NullPhoneVerifier. The result is a status and the
 * line type (mobile / landline / voip) when known.
 */
interface PhoneVerifier
{
    public const string VALID = 'valid';

    public const string INVALID = 'invalid';

    public const string UNKNOWN = 'unknown';

    public const string UNVERIFIED = 'unverified';

    /**
     * @return array{status: string, line: ?string}
     */
    public function verify(string $phone): array;
}
