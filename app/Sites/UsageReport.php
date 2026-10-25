<?php

namespace App\Sites;

use App\Models\Owner;

/**
 * Rolls up an owner's usage against their plan's allowances across the three
 * metered dimensions — build minutes, traffic (views) and storage — with the
 * accrued cost beyond each free allowance. Display and data model only; nothing
 * is charged.
 */
class UsageReport
{
    /**
     * @return array<string, mixed>
     */
    public static function for(Owner $owner): array
    {
        $plan = $owner->plan();

        $buildMs = (int) $owner->build_ms_total;
        $freeBuildMs = (int) $plan['free_build_minutes'] * 60_000;

        $views = (int) $owner->sites()->sum('view_count');
        $viewAllowance = (int) $plan['monthly_views'];

        $storageBytes = (int) $owner->sites()->withSum('assets', 'size')->get()->sum('assets_sum_size');
        $storageAllowance = (int) $plan['storage_mb'] * 1_048_576;

        $metering = config('plans.metering');

        return [
            'plan' => $plan,
            'balance_cents' => (int) $owner->build_balance_cents,
            'build' => [
                'used_ms' => $buildMs,
                'free_ms' => $freeBuildMs,
                'free_remaining_ms' => max(0, $freeBuildMs - $buildMs),
                'pct' => self::pct($buildMs, $freeBuildMs),
                'over' => $buildMs > $freeBuildMs,
            ],
            'traffic' => [
                'used' => $views,
                'allowance' => $viewAllowance,
                'pct' => self::pct($views, $viewAllowance),
                'over' => $views > $viewAllowance,
                'overage_cents' => $views > $viewAllowance
                    ? (int) ceil(($views - $viewAllowance) / 1_000 * (int) $metering['traffic_cents_per_1k_views'])
                    : 0,
            ],
            'storage' => [
                'used_bytes' => $storageBytes,
                'allowance_bytes' => $storageAllowance,
                'pct' => self::pct($storageBytes, $storageAllowance),
                'over' => $storageBytes > $storageAllowance,
                'overage_cents' => $storageBytes > $storageAllowance
                    ? (int) ceil(($storageBytes - $storageAllowance) / 1_073_741_824 * (int) $metering['storage_cents_per_gb_month'])
                    : 0,
            ],
        ];
    }

    protected static function pct(int $used, int $allowance): int
    {
        if ($allowance <= 0) {
            return $used > 0 ? 100 : 0;
        }

        return (int) min(100, round($used / $allowance * 100));
    }
}
