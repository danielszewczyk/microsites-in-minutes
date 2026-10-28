<?php

namespace App\Jobs;

use App\Models\Site;
use App\Models\SiteAlarm;
use App\Models\SiteBreakdown;
use App\Models\SiteHourlyStat;
use App\Models\SiteMinuteStat;
use App\Models\SiteStat;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

/**
 * Records one view of a published page. Dispatched after the response is sent
 * so that serving the (cached) page never blocks on a write — the pattern that
 * keeps published pages cheap and fast under load. Entries are counted inline
 * because they are rare and the owner wants them to be exact.
 *
 * Every hit is rolled up into three time tiers — minute, hour and day — so the
 * dashboard chart can zoom from the last hour down to the minute or back across
 * months, and into the source / campaign / device breakdowns so the dashboard
 * can show where traffic comes from.
 *
 * @param  array{source?: string, campaign?: ?string, device?: string}  $dims
 */
class RecordSiteHit implements ShouldQueue
{
    use Queueable;

    /**
     * @param  string  $at  ISO-8601 timestamp of the hit (so the minute/hour buckets are exact).
     * @param  array<string, string|null>  $dims
     */
    public function __construct(public int $siteId, public string $at, public array $dims = []) {}

    public function handle(): void
    {
        Site::withoutTimestamps(fn () => Site::query()->whereKey($this->siteId)->increment('view_count'));

        self::record($this->siteId, 'views', Carbon::parse($this->at));
        self::breakdowns($this->siteId, $this->dims, 'views');

        if ($site = Site::query()->find($this->siteId)) {
            SiteAlarm::checkFor($site, [SiteAlarm::VIEWS]);
        }
    }

    /**
     * Increment one counter ('views' or 'entries') across all three time tiers
     * for a single hit, so views and entries roll up identically. The shared
     * entry point for both the view job and the inline entry counter.
     */
    public static function record(int $siteId, string $column, Carbon $at): void
    {
        self::bump($siteId, $at->toDateString(), $column);
        self::bumpHour($siteId, $at->copy()->startOfHour(), $column);
        self::bumpMinute($siteId, $at->copy()->startOfMinute(), $column);
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
     * Increment one counter on a site's hour bucket (top of the hour).
     */
    public static function bumpHour(int $siteId, Carbon $hour, string $column): void
    {
        self::bumpBucket(SiteHourlyStat::class, 'stat_hour', $siteId, $hour, $column);
    }

    /**
     * Increment one counter on a site's minute bucket (top of the minute).
     */
    public static function bumpMinute(int $siteId, Carbon $minute, string $column): void
    {
        self::bumpBucket(SiteMinuteStat::class, 'stat_minute', $siteId, $minute, $column);
    }

    /**
     * Shared increment-or-create for a time-bucketed stat row, mirroring bump()
     * and tolerating the race where another worker creates the bucket first.
     *
     * @param  class-string<Model>  $model
     */
    protected static function bumpBucket(string $model, string $timeColumn, int $siteId, Carbon $at, string $column): void
    {
        $affected = $model::query()
            ->where('site_id', $siteId)
            ->where($timeColumn, $at)
            ->increment($column);

        if ($affected === 0) {
            try {
                $model::query()->create([
                    'site_id' => $siteId,
                    $timeColumn => $at,
                    'views' => $column === 'views' ? 1 : 0,
                    'entries' => $column === 'entries' ? 1 : 0,
                ]);
            } catch (QueryException) {
                $model::query()->where('site_id', $siteId)->where($timeColumn, $at)->increment($column);
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
    public static function forNow(int $siteId, array $dims = []): self
    {
        return new self($siteId, Carbon::now()->toIso8601String(), $dims);
    }
}
