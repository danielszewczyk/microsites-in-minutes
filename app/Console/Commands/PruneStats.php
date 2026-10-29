<?php

namespace App\Console\Commands;

use App\Models\SiteClickEvent;
use App\Models\SiteHourlyStat;
use App\Models\SiteMinuteStat;
use Illuminate\Console\Command;

/**
 * Enforces the analytics retention windows: minute buckets are kept for ~2 days,
 * hourly buckets for ~90 days, and raw click events for ~7 days; the daily tiers
 * (site_stats, site_click_stats) are long-term and never pruned. Scheduled daily
 * (see routes/console.php).
 */
class PruneStats extends Command
{
    /** Keep per-minute detail for this many days. */
    public const int MINUTE_DAYS = 2;

    /** Keep per-hour detail for this many days. */
    public const int HOUR_DAYS = 90;

    /**
     * Keep raw click events for this many days. The durable counts live in the
     * daily roll-up (site_click_stats); the raw rows are only a short firehose,
     * so this window is what stops click tracking becoming an unbounded table.
     */
    public const int CLICK_EVENT_DAYS = 7;

    protected $signature = 'stats:prune';

    protected $description = 'Prune minute, hourly and raw click buckets past their retention windows';

    public function handle(): int
    {
        $minutes = SiteMinuteStat::query()
            ->where('stat_minute', '<', now()->subDays(self::MINUTE_DAYS))
            ->delete();

        $hours = SiteHourlyStat::query()
            ->where('stat_hour', '<', now()->subDays(self::HOUR_DAYS))
            ->delete();

        $clicks = SiteClickEvent::query()
            ->where('occurred_at', '<', now()->subDays(self::CLICK_EVENT_DAYS))
            ->delete();

        $this->info("Pruned {$minutes} minute rows, {$hours} hourly rows and {$clicks} raw click rows.");

        return self::SUCCESS;
    }
}
