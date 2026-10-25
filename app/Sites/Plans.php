<?php

namespace App\Sites;

use App\Models\Owner;

/**
 * Reads the tier definitions in config/plans.php and answers the questions the
 * app asks of them: what a tier allows, and whether an owner is within it. This
 * is the enforcement seam for the monetization model; no money is charged.
 */
class Plans
{
    /**
     * @return array<string, mixed>
     */
    public static function tier(?string $name): array
    {
        $tiers = config('plans.tiers');
        $key = $name !== null && isset($tiers[$name]) ? $name : config('plans.default');

        return ['key' => $key] + $tiers[$key];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return config('plans.tiers');
    }

    /**
     * A generous per-owner site cap during the launch promotion, so "free while
     * in beta" is true in practice and not blocked at the first extra site.
     */
    public const int BETA_MAX_SITES = 25;

    /**
     * Whether the owner may publish another site. During the launch promotion a
     * generous flat cap applies instead of the (small) free-tier limit.
     */
    public static function canPublishAnother(Owner $owner): bool
    {
        $cap = self::launchFree() ? self::BETA_MAX_SITES : (int) $owner->plan()['max_sites'];

        return $owner->sites()->count() < $cap;
    }

    /**
     * Whether the launch promotion is on — every build free, no accrued cost.
     */
    public static function launchFree(): bool
    {
        return (bool) config('plans.launch_free');
    }
}
