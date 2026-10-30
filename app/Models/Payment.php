<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A credited Stripe payment, keyed by a unique reference so the same payment is
 * never applied twice. See the create_payments_table migration.
 *
 * @property string $reference
 */
class Payment extends Model
{
    protected $guarded = [];

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
