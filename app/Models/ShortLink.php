<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A short, shareable link to a site carrying UTM tags. Redirects to the site
 * with those tags so the visit is attributed in reporting.
 *
 * @property string $code
 * @property ?string $label
 * @property ?string $source
 * @property ?string $medium
 * @property ?string $campaign
 * @property int $clicks
 */
class ShortLink extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['clicks' => 'integer'];
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * The full destination: the site's URL with the UTM tags appended.
     */
    public function targetUrl(): string
    {
        $query = array_filter([
            'utm_source' => $this->source,
            'utm_medium' => $this->medium,
            'utm_campaign' => $this->campaign,
        ], fn ($value): bool => $value !== null && $value !== '');

        $url = $this->site->url();

        return $query === [] ? $url : $url.'?'.http_build_query($query);
    }

    public function shortUrl(): string
    {
        return route('link.redirect', $this->code);
    }

    public static function freeCode(): string
    {
        do {
            $code = Str::lower(Str::random(7));
        } while (self::query()->where('code', $code)->exists());

        return $code;
    }
}
