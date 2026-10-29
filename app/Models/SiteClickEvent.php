<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One raw, anonymous click on a tracked link or button: the short-retention
 * firehose behind the Pro click-tracking add-on. Holds no visitor identity —
 * only the stable element id, the link's href and when it happened. Pruned by
 * stats:prune; the durable counts live in SiteClickStat.
 *
 * @property string $element_id
 * @property ?string $href
 * @property Carbon $occurred_at
 */
class SiteClickEvent extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
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
