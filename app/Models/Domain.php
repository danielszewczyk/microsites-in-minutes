<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A vanity domain bought for a site. The app serves the site on this domain by
 * matching the request Host once the domain is active.
 *
 * @property string $domain
 * @property string $status
 * @property int $price_cents
 * @property bool $auto_renew
 */
class Domain extends Model
{
    public const string REGISTERING = 'registering';

    public const string PENDING_DNS = 'pending_dns';

    public const string ACTIVE = 'active';

    public const string FAILED = 'failed';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'auto_renew' => 'boolean',
            'verified_at' => 'datetime',
            'expires_at' => 'datetime',
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

    /**
     * @param  Builder<Domain>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', self::ACTIVE);
    }

    public function isActive(): bool
    {
        return $this->status === self::ACTIVE;
    }

    public function isRegistering(): bool
    {
        return $this->status === self::REGISTERING;
    }

    /**
     * Normalise a hostname a buyer typed: lower-case, no scheme, no path, no
     * leading www. Returns null when it is not a plausible domain.
     */
    public static function normalise(string $value): ?string
    {
        $value = strtolower(trim($value));
        $value = (string) preg_replace('#^https?://#', '', $value);
        $value = (string) preg_replace('#[/?].*$#', '', $value);
        $value = (string) preg_replace('/^www\./', '', $value);

        return preg_match('/^(?=.{4,253}$)([a-z0-9](-?[a-z0-9])*\.)+[a-z]{2,}$/', $value) === 1 ? $value : null;
    }
}
