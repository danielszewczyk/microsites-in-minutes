<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One hour's view and entry totals for a site: the mid analytics tier between
 * the per-minute and per-day rollups.
 *
 * @property Carbon $stat_hour
 * @property int $views
 * @property int $entries
 */
class SiteHourlyStat extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'stat_hour' => 'datetime',
            'views' => 'integer',
            'entries' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
