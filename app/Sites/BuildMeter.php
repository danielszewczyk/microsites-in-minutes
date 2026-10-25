<?php

namespace App\Sites;

use App\Models\BuildEvent;
use App\Models\Owner;
use App\Models\Site;

/**
 * Meters build time and bills for it. Every build — a publish or a regenerate —
 * is timed; the first minute of build time is free on every tier, and time
 * beyond that accrues onto the owner's balance at the configured per-minute
 * rate. Nothing is actually charged (the Stripe stub); this only records what
 * a charge would be.
 */
class BuildMeter
{
    /**
     * Record one build against an owner and return the ledger event. Updates
     * the owner's cumulative build time and accrued balance in one step.
     */
    public static function record(Owner $owner, ?Site $site, string $kind, int $durationMs): BuildEvent
    {
        $cents = self::costCents($owner, $durationMs);

        $event = BuildEvent::query()->create([
            'owner_id' => $owner->id,
            'site_id' => $site?->id,
            'kind' => $kind,
            'duration_ms' => $durationMs,
            'cost_cents' => $cents,
            'created_at' => now(),
        ]);

        $owner->increment('build_ms_total', $durationMs);

        if ($cents > 0) {
            $owner->increment('build_balance_cents', $cents);
        }

        return $event;
    }

    /**
     * What this build costs: only the portion of its time that falls beyond the
     * owner's remaining free allowance, at the per-minute rate.
     */
    public static function costCents(Owner $owner, int $durationMs): int
    {
        $freeMs = (int) $owner->plan()['free_build_minutes'] * 60_000;
        $before = (int) $owner->build_ms_total;
        $after = $before + $durationMs;

        $billableMs = max(0, $after - max($before, $freeMs));

        if ($billableMs <= 0) {
            return 0;
        }

        $rate = (int) config('plans.metering.build_cents_per_minute');

        return (int) ceil($billableMs / 60_000 * $rate);
    }

    /**
     * Milliseconds of free build time an owner still has.
     */
    public static function freeMsRemaining(Owner $owner): int
    {
        $freeMs = (int) $owner->plan()['free_build_minutes'] * 60_000;

        return max(0, $freeMs - (int) $owner->build_ms_total);
    }
}
