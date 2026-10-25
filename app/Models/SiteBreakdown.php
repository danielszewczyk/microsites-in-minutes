<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cumulative views and entries for one site, split by a dimension's value —
 * e.g. dimension "source", key "google". The spine of the built-in reporting
 * breakdowns.
 *
 * @property string $dimension
 * @property string $key
 * @property int $views
 * @property int $entries
 */
class SiteBreakdown extends Model
{
    public const string SOURCE = 'source';

    public const string CAMPAIGN = 'campaign';

    public const string DEVICE = 'device';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['views' => 'integer', 'entries' => 'integer'];
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
