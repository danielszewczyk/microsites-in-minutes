<?php

namespace App\Console\Commands;

use App\Models\SiteHourlyStat;
use App\Models\SiteMinuteStat;
use Illuminate\Console\Command;

/**
 * Enforces the analytics retention windows: minute buckets are kept for ~2 days
 * and hourly buckets for ~90 days; the daily tier (site_stats) is long-term and
 * never pruned. Scheduled daily (see routes/console.php).
 */
class PruneStats extends Command
{
    /** Keep per-minute detail for this many days. */
    public const int MINUTE_DAYS = 2;

    /** Keep per-hour detail for this many days. */
    public const int HOUR_DAYS = 90;

    protected $signature = 'stats:prune';

    protected $description = 'Prune minute and hourly analytics buckets past their retention windows';

    public function handle(): int
    {
        $minutes = SiteMinuteStat::query()
            ->where('stat_minute', '<', now()->subDays(self::MINUTE_DAYS))
            ->delete();

        $hours = SiteHourlyStat::query()
            ->where('stat_hour', '<', now()->subDays(self::HOUR_DAYS))
            ->delete();

        $this->info("Pruned {$minutes} minute rows and {$hours} hourly rows.");

        return self::SUCCESS;
    }
}
