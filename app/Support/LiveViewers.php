<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * An approximate "viewing now" count. Each open page beats every few seconds
 * with a per-tab id; a beat counts as live for a short window, and stale ids are
 * pruned. Deliberately lightweight — one cache entry per site, no broadcasting
 * or per-viewer rows — so the number is a live-ish indicator, not an exact gauge
 * (a simultaneous pair of beats can briefly drop one, which self-heals next beat).
 */
class LiveViewers
{
    /** Seconds a beat is counted as "live" (a little over the client's beat interval). */
    public const int WINDOW = 15;

    /**
     * Record that a visitor is on the page right now; returns the current count.
     */
    public static function beat(int $siteId, string $visitorId): int
    {
        $visitorId = substr((string) preg_replace('/[^A-Za-z0-9]/', '', $visitorId), 0, 32);

        if ($visitorId === '') {
            return self::count($siteId);
        }

        $now = time();
        $seen = self::fresh((array) Cache::get(self::key($siteId), []), $now);
        $seen[$visitorId] = $now;
        Cache::put(self::key($siteId), $seen, now()->addSeconds(self::WINDOW * 3));

        return count($seen);
    }

    public static function count(int $siteId): int
    {
        return count(self::fresh((array) Cache::get(self::key($siteId), []), time()));
    }

    /**
     * @param  array<string, mixed>  $seen
     * @return array<string, int>
     */
    protected static function fresh(array $seen, int $now): array
    {
        return array_filter($seen, fn ($t): bool => is_int($t) && $t > $now - self::WINDOW);
    }

    protected static function key(int $siteId): string
    {
        return "live:{$siteId}";
    }
}
