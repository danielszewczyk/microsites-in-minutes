<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cloudflare Turnstile verification for the public entry form. The widget on
 * the page produces a token; this confirms it server-side with Cloudflare
 * before a verification code is sent or an entry is accepted.
 *
 * Turnstile is enforced only when both keys are configured. Without them
 * verify() returns true, so the entry/code flow still works and the bot
 * protection switches on the moment the keys are added — no code change needed.
 * Managed/invisible presentation is chosen in the Cloudflare dashboard, not here.
 */
class Turnstile
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /**
     * Whether Turnstile is configured and should therefore be enforced.
     */
    public static function enabled(): bool
    {
        return filled(config('services.turnstile.site_key')) && filled(config('services.turnstile.secret'));
    }

    /**
     * The public site key for the widget, or null when Turnstile is off.
     */
    public static function siteKey(): ?string
    {
        return config('services.turnstile.site_key');
    }

    /**
     * Confirm a widget token with Cloudflare. Returns true when Turnstile is
     * not configured (nothing to enforce) or the token is valid for this IP.
     *
     * Fails closed: a missing or empty token, a non-success verdict, or a
     * network/service error all return false. Turnstile is a bot gate, so when
     * it cannot confirm a human it must not wave the request through — the risk
     * of mail-bombing and bot entries during an outage outweighs the rare
     * false rejection while Cloudflare is unreachable.
     */
    public static function verify(?string $token, ?string $ip = null): bool
    {
        if (! self::enabled()) {
            return true;
        }

        if (blank($token)) {
            return false;
        }

        try {
            $response = Http::asForm()->timeout(5)->post(self::VERIFY_URL, array_filter([
                'secret' => config('services.turnstile.secret'),
                'response' => $token,
                'remoteip' => $ip,
            ]));

            return (bool) ($response->json('success') ?? false);
        } catch (\Throwable $e) {
            Log::warning('Turnstile verification error', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
