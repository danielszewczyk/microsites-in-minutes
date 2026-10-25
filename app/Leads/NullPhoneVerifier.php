<?php

namespace App\Leads;

/**
 * Used when no phone-lookup provider is configured: nothing is checked and the
 * entry is marked "unverified".
 */
class NullPhoneVerifier implements PhoneVerifier
{
    /**
     * @return array{status: string, line: ?string}
     */
    public function verify(string $phone): array
    {
        return ['status' => self::UNVERIFIED, 'line' => null];
    }
}
