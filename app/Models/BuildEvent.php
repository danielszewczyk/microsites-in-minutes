<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One billed build: a site publish or regenerate, with how long it took and
 * what it cost. The metering ledger behind an owner's build balance.
 *
 * @property string $kind
 * @property int $duration_ms
 * @property int $cost_cents
 */
class BuildEvent extends Model
{
    public const string KIND_CREATE = 'create';

    public const string KIND_REGENERATE = 'regenerate';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'duration_ms' => 'integer',
            'cost_cents' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Owner, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
