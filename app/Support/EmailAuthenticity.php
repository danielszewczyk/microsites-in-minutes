<?php

namespace App\Support;

/**
 * Near-free authenticity checks run before a verification code is emailed, to
 * avoid spending a send on an address that can't or shouldn't receive one:
 *  - a blocklist of disposable / temp-mail domains, and
 *  - a DNS check that the domain actually has a mail server.
 *
 * These sit in the cost-ordered funnel between Turnstile (free) and the email
 * send. They are an enhancement, not a hard gate — the MX check fails open on
 * any DNS trouble so a flaky lookup never blocks a real entrant.
 */
class EmailAuthenticity
{
    /**
     * Common disposable / throwaway mail domains. Matched on the exact domain
     * or any subdomain of it. Kept deliberately small and obvious; grow it as
     * real abuse shows up rather than chasing an exhaustive list.
     *
     * @var list<string>
     */
    public const DISPOSABLE = [
        'mailinator.com',
        'guerrillamail.com',
        'guerrillamail.info',
        'sharklasers.com',
        'grr.la',
        'yopmail.com',
        'yopmail.net',
        '10minutemail.com',
        '10minutemail.net',
        'tempmail.com',
        'temp-mail.org',
        'trashmail.com',
        'getnada.com',
        'dispostable.com',
        'maildrop.cc',
        'mailnesia.com',
        'fakeinbox.com',
        'throwawaymail.com',
        'mintemail.com',
        'mohmal.com',
        'spamgourmet.com',
        'emailondeck.com',
        'moakt.com',
        'tempinbox.com',
        'discard.email',
    ];

    /**
     * Whether the email's domain is a known disposable / temp-mail provider.
     */
    public static function isDisposable(string $email): bool
    {
        $domain = self::domain($email);

        if ($domain === '') {
            return false;
        }

        foreach (self::DISPOSABLE as $blocked) {
            if ($domain === $blocked || str_ends_with($domain, '.'.$blocked)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the email's domain can plausibly receive mail: it has an MX
     * record, or (per RFC 5321's implicit-MX rule) at least an A/AAAA record.
     *
     * Fails open: an empty/garbled domain is false, but any DNS error or a
     * timeout returns true so a lookup problem never rejects a real address.
     * A resolver may be injected for testing; in the test environment the DNS
     * call is skipped (returns true) unless one is.
     *
     * @param  (callable(string): bool)|null  $resolver
     */
    public static function hasMailServer(string $email, ?callable $resolver = null): bool
    {
        $domain = self::domain($email);

        if ($domain === '') {
            return false;
        }

        if ($resolver === null && app()->environment('testing')) {
            return true;
        }

        $resolver ??= static fn (string $d): bool => checkdnsrr($d, 'MX') || checkdnsrr($d, 'A') || checkdnsrr($d, 'AAAA');

        try {
            return (bool) $resolver($domain);
        } catch (\Throwable) {
            return true;
        }
    }

    /**
     * The lower-cased domain part of an email, or '' when there isn't one.
     */
    private static function domain(string $email): string
    {
        $at = strrchr(trim($email), '@');

        return $at === false ? '' : mb_strtolower(substr($at, 1));
    }
}
