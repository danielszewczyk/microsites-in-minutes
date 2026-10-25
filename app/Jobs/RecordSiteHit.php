<?php

namespace App\Jobs;

use App\Models\Site;
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
 */
class RecordSiteHit implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $siteId, public string $day) {}

    public function handle(): void
    {
        Site::query()->whereKey($this->siteId)->increment('view_count');

        self::bump($this->siteId, $this->day, 'views');
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

    public static function forToday(int $siteId): self
    {
        return new self($siteId, Carbon::today()->toDateString());
    }
}
