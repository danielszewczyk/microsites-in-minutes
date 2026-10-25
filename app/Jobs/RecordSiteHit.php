<?php

namespace App\Jobs;

use App\Models\Site;
use App\Models\SiteAlarm;
use App\Models\SiteBreakdown;
use App\Models\SiteStat;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

/**
 * Records one view of a published page. Dispatched after the response is sent
 * so that serving the (cached) page never blocks on a write — the pattern that
 * keeps published pages cheap and fast under load. Entries are counted inline
 * because they are rare and the owner wants them to be exact.
 *
 * It also rolls up the view into the reporting breakdowns (source, campaign,
 * device) so the dashboard can show where traffic comes from.
 *
 * @param  array{source?: string, campaign?: ?string, device?: string}  $dims
 */
class RecordSiteHit implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, string|null>  $dims
     */
    public function __construct(public int $siteId, public string $day, public array $dims = []) {}

    public function handle(): void
    {
        Site::query()->whereKey($this->siteId)->increment('view_count');

        self::bump($this->siteId, $this->day, 'views');
        self::breakdowns($this->siteId, $this->dims, 'views');

        if ($site = Site::query()->find($this->siteId)) {
            SiteAlarm::checkFor($site, [SiteAlarm::VIEWS]);
        }
    }

    /**
     * Increment one counter on a site's day row, creating the row the first
     * time. Portable across SQLite and Postgres (no upsert expression).
     */
    public static function bump(int $siteId, string $day, string $column): void
    {
        $affected = SiteStat::query()
            ->where('site_id', $siteId)
            ->where('stat_date', $day)
            ->increment($column);

        if ($affected === 0) {
            try {
                SiteStat::query()->create([
                    'site_id' => $siteId,
                    'stat_date' => $day,
                    'views' => $column === 'views' ? 1 : 0,
                    'entries' => $column === 'entries' ? 1 : 0,
                ]);
            } catch (QueryException) {
                // Another worker created the row first; just add to it.
                SiteStat::query()->where('site_id', $siteId)->where('stat_date', $day)->increment($column);
            }
        }
    }

    /**
     * Roll one hit into the source / campaign / device breakdowns.
     *
     * @param  array<string, string|null>  $dims
     */
    public static function breakdowns(int $siteId, array $dims, string $column): void
    {
        $pairs = [
            SiteBreakdown::SOURCE => $dims['source'] ?? null,
            SiteBreakdown::CAMPAIGN => $dims['campaign'] ?? null,
            SiteBreakdown::DEVICE => $dims['device'] ?? null,
        ];

        foreach ($pairs as $dimension => $key) {
            if ($key === null || $key === '') {
                continue;
            }

            self::bumpBreakdown($siteId, $dimension, (string) $key, $column);
        }
    }

    protected static function bumpBreakdown(int $siteId, string $dimension, string $key, string $column): void
    {
        $affected = SiteBreakdown::query()
            ->where('site_id', $siteId)->where('dimension', $dimension)->where('key', $key)
            ->increment($column);

        if ($affected === 0) {
            try {
                SiteBreakdown::query()->create([
                    'site_id' => $siteId,
                    'dimension' => $dimension,
                    'key' => $key,
                    'views' => $column === 'views' ? 1 : 0,
                    'entries' => $column === 'entries' ? 1 : 0,
                ]);
            } catch (QueryException) {
                SiteBreakdown::query()
                    ->where('site_id', $siteId)->where('dimension', $dimension)->where('key', $key)
                    ->increment($column);
            }
        }
    }

    /**
     * @param  array<string, string|null>  $dims
     */
    public static function forToday(int $siteId, array $dims = []): self
    {
        return new self($siteId, Carbon::today()->toDateString(), $dims);
    }
}
