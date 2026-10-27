<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

/**
 * Short-lived 6-digit email verification codes for entry forms. A code lives
 * only in the cache, keyed by site and email, never in the database and never
 * logged. Each is single-use, expires after a few minutes, and burns itself
 * after a handful of wrong guesses. Only a hash of the code is stored, so the
 * cache never holds the plain code either.
 */
class EntryCode
{
    /** Minutes a code stays valid. */
    public const TTL_MINUTES = 10;

    /** Wrong guesses allowed before the code is burned. */
    public const MAX_ATTEMPTS = 5;

    /** Seconds a caller must wait before a fresh code is sent. */
    public const RESEND_SECONDS = 30;

    /**
     * Issue a new 6-digit code for an email on a site, store its hash, and
     * return the plain code for the caller to email. Returns null when a code
     * was issued within the resend window, so a new one is not sent too soon.
     */
    public static function issue(int $siteId, string $email): ?string
    {
        $key = self::key($siteId, $email);
        $existing = Cache::get($key);
        $now = now()->getTimestamp();

        if (is_array($existing) && ($now - (int) ($existing['issued_at'] ?? 0)) < self::RESEND_SECONDS) {
            return null;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put($key, [
            'hash' => Hash::make($code),
            'attempts' => 0,
            'issued_at' => $now,
            'expires_at' => $now + self::TTL_MINUTES * 60,
        ], now()->addMinutes(self::TTL_MINUTES));

        return $code;
    }

    /**
     * Whether a code is currently pending for this email on this site.
     */
    public static function pending(int $siteId, string $email): bool
    {
        return is_array(Cache::get(self::key($siteId, $email)));
    }

    /**
     * Seconds remaining before a fresh code may be requested, for the client
     * countdown. Zero when none is pending or the window has already passed.
     */
    public static function retryAfter(int $siteId, string $email): int
    {
        $existing = Cache::get(self::key($siteId, $email));

        if (! is_array($existing)) {
            return 0;
        }

        return max(0, self::RESEND_SECONDS - (now()->getTimestamp() - (int) ($existing['issued_at'] ?? 0)));
    }

    /**
     * Check a submitted code. On a match the code is burned (single-use) and
     * true is returned. A wrong guess counts against the attempt limit and
     * burns the code once the limit is reached. A missing or expired code is
     * false. The retained record keeps its original expiry.
     */
    public static function check(int $siteId, string $email, string $code): bool
    {
        $key = self::key($siteId, $email);

        // Serialise the read-modify-write so concurrent guesses cannot each read
        // attempts=0 (bypassing the cap) and so a code is consumed exactly once.
        // A contended check returns false rather than waiting.
        return (bool) Cache::lock('lock:'.$key, 5)->get(function () use ($key, $code): bool {
            $record = Cache::get($key);

            if (! is_array($record) || ! isset($record['hash'])) {
                return false;
            }

            if (Hash::check($code, $record['hash'])) {
                Cache::forget($key);

                return true;
            }

            $record['attempts'] = (int) ($record['attempts'] ?? 0) + 1;

            if ($record['attempts'] >= self::MAX_ATTEMPTS) {
                Cache::forget($key);

                return false;
            }

            $expiresAt = Carbon::createFromTimestamp((int) ($record['expires_at'] ?? now()->getTimestamp()));

            if ($expiresAt->isFuture()) {
                Cache::put($key, $record, $expiresAt);
            } else {
                Cache::forget($key);
            }

            return false;
        });
    }

    private static function key(int $siteId, string $email): string
    {
        return 'entrycode:'.$siteId.':'.hash('sha256', mb_strtolower(trim($email)));
    }
}
