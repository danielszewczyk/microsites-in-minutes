<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One minute's view and entry totals for a site: the finest analytics tier.
 *
 * @property Carbon $stat_minute
 * @property int $views
 * @property int $entries
 */
class SiteMinuteStat extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'stat_minute' => 'datetime',
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
