<?php

namespace App\Models;

use Database\Factories\SiteEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A lead or sweepstakes entry captured by a microsite's form.
 *
 * @property string $name
 * @property ?string $email
 * @property ?string $phone
 * @property ?string $message
 * @property array<string, mixed>|null $extra
 * @property bool $is_amoe
 */
class SiteEntry extends Model
{
    /** @use HasFactory<SiteEntryFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'extra' => 'array',
            'is_amoe' => 'boolean',
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
