<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One day's click total for a single tracked element of a site: the durable,
 * forward-only roll-up behind the Pro click-tracking add-on. Keyed by the
 * element's stable publish-time id so counts survive label changes.
 *
 * @property string $element_id
 * @property Carbon $stat_date
 * @property int $clicks
 */
class SiteClickStat extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'stat_date' => 'date',
            'clicks' => 'integer',
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
