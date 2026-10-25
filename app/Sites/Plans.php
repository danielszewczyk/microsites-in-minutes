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
     * Whether the owner may publish another site under their plan's cap.
     */
    public static function canPublishAnother(Owner $owner): bool
    {
        return $owner->sites()->count() < (int) $owner->plan()['max_sites'];
    }
}
