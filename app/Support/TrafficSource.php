<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Works out where a visit came from and on what kind of device, cookielessly
 * and server-side — no tracker, no consent banner. A view is attributed from
 * the request (referrer + utm query); an entry is attributed from values the
 * page's own script sends with the form (the landing referrer/utm), since the
 * cached page cannot bake per-visitor values in.
 */
class TrafficSource
{
    /**
     * Referrer host (without leading www) → a tidy source name.
     *
     * @var array<string, string>
     */
    protected const array HOSTS = [
        'google' => 'google', 'bing' => 'bing', 'duckduckgo' => 'duckduckgo', 'yahoo' => 'yahoo',
        'facebook' => 'facebook', 'instagram' => 'instagram', 'l.instagram' => 'instagram',
        'twitter' => 'twitter', 'x' => 'twitter', 't.co' => 'twitter',
        'linkedin' => 'linkedin', 'lnkd' => 'linkedin', 'youtube' => 'youtube', 'youtu.be' => 'youtube',
        'tiktok' => 'tiktok', 'reddit' => 'reddit', 'pinterest' => 'pinterest',
    ];

    /**
     * Attribution for a page view, from the request itself.
     *
     * @return array{source: string, campaign: ?string, device: string}
     */
    public static function fromRequest(Request $request): array
    {
        return self::resolve(
            (string) $request->query('utm_source', ''),
            (string) $request->query('utm_campaign', ''),
            (string) $request->headers->get('referer', ''),
            (string) $request->userAgent(),
        );
    }

    /**
     * Attribution for an entry, from the values the page script posts plus the
     * request's own user agent.
     *
     * @return array{source: string, campaign: ?string, device: string}
     */
    public static function fromInput(Request $request): array
    {
        return self::resolve(
            (string) $request->input('utm_source', ''),
            (string) $request->input('utm_campaign', ''),
            (string) $request->input('referrer', ''),
            (string) $request->userAgent(),
        );
    }

    /**
     * @return array{source: string, campaign: ?string, device: string}
     */
    protected static function resolve(string $utmSource, string $utmCampaign, string $referrer, string $userAgent): array
    {
        $source = self::clean($utmSource);

        if ($source === '') {
            $source = self::fromReferrer($referrer);
        }

        return [
            'source' => $source !== '' ? $source : 'direct',
            'campaign' => self::clean($utmCampaign) ?: null,
            'device' => self::device($userAgent),
        ];
    }

    protected static function fromReferrer(string $referrer): string
    {
        $host = strtolower((string) parse_url(trim($referrer), PHP_URL_HOST));

        if ($host === '') {
            return '';
        }

        $host = (string) preg_replace('/^www\./', '', $host);

        if (isset(self::HOSTS[$host])) {
            return self::HOSTS[$host];
        }

        // A known engine/network as a subdomain or regional TLD (e.g. google.co.uk, m.facebook.com).
        foreach (self::HOSTS as $needle => $name) {
            if (str_contains($host, $needle.'.')) {
                return $name;
            }
        }

        return $host;
    }

    protected static function device(string $userAgent): string
    {
        if (preg_match('/iPad|Tablet|PlayBook|Silk|Kindle/i', $userAgent) === 1) {
            return 'tablet';
        }

        if (preg_match('/Mobi|Android.*Mobile|iPhone|iPod|Windows Phone/i', $userAgent) === 1) {
            return 'mobile';
        }

        return 'desktop';
    }

    protected static function clean(string $value): string
    {
        return Str::limit(strtolower(trim(preg_replace('/[^\w .\-]/', '', $value) ?? '')), 40, '');
    }
}
