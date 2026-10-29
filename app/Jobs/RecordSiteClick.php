<?php

namespace App\Jobs;

use App\Models\SiteClickEvent;
use App\Models\SiteClickStat;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

/**
 * Records one click on a tracked link or button (the Pro click-tracking
 * add-on). Dispatched after the response is sent so the lightweight ingest
 * endpoint never blocks on a write — the same pattern that keeps published
 * pages cheap under load (see RecordSiteHit).
 *
 * The click is written twice: a short-lived raw row (the firehose, pruned by
 * stats:prune) and a forward-only increment on the durable daily roll-up keyed
 * by the element's stable id. Everything stored is anonymous and cookieless —
 * no visitor identity, IP or user agent.
 */
class RecordSiteClick implements ShouldQueue
{
    use Queueable;

    /**
     * @param  string  $at  ISO-8601 timestamp of the click (so the day bucket is exact).
     */
    public function __construct(
        public int $siteId,
        public string $elementId,
        public ?string $href,
        public string $at,
    ) {}

    public function handle(): void
    {
        self::record($this->siteId, $this->elementId, $this->href, Carbon::parse($this->at));
    }

    /**
     * Store one click: a raw event row plus a forward-only bump of the element's
     * day total. The shared entry point so a test can record directly.
     */
    public static function record(int $siteId, string $elementId, ?string $href, Carbon $at): void
    {
        SiteClickEvent::query()->create([
            'site_id' => $siteId,
            'element_id' => $elementId,
            'href' => $href,
            'occurred_at' => $at,
        ]);

        self::bump($siteId, $elementId, $at->toDateString());
    }

    /**
     * Increment one element's click count on its day row, creating the row the
     * first time. Mirrors RecordSiteHit::bump() (forward-only, no upsert
     * expression, tolerant of the create race), but matches the day with
     * whereDate so it is correct on SQLite too — a bare date column there keeps
     * the cast's 'Y-m-d 00:00:00' and a plain equality on 'Y-m-d' would miss.
     */
    public static function bump(int $siteId, string $elementId, string $day): void
    {
        $affected = self::dayRow($siteId, $elementId, $day)->increment('clicks');

        if ($affected === 0) {
            try {
                SiteClickStat::query()->create([
                    'site_id' => $siteId,
                    'element_id' => $elementId,
                    'stat_date' => $day,
                    'clicks' => 1,
                ]);
            } catch (QueryException) {
                // Another worker created the row first; just add to it.
                self::dayRow($siteId, $elementId, $day)->increment('clicks');
            }
        }
    }

    /**
     * The query for one element's day row.
     *
     * @return Builder<SiteClickStat>
     */
    protected static function dayRow(int $siteId, string $elementId, string $day): Builder
    {
        return SiteClickStat::query()
            ->where('site_id', $siteId)
            ->where('element_id', $elementId)
            ->whereDate('stat_date', $day);
    }

    public static function forNow(int $siteId, string $elementId, ?string $href): self
    {
        return new self($siteId, $elementId, $href, Carbon::now()->toIso8601String());
    }
}
