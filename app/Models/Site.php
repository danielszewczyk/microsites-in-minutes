<?php

namespace App\Models;

use App\Sites\HeroArt;
use Database\Factories\SiteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A one-page microsite generated from an owner's prompt and published at
 * /s/{slug}. The owner's raw inputs (what a regenerate re-reads) and the
 * generated content are JSON documents; settings holds accent colour, hero
 * picture, GA4 id and branding flags.
 *
 * @property string $slug
 * @property string $type
 * @property string $status
 * @property array<string, mixed> $inputs
 * @property array<string, mixed> $content
 * @property array<string, mixed> $settings
 * @property int $view_count
 * @property int $entry_count
 * @property ?Carbon $published_at
 */
class Site extends Model
{
    /** @use HasFactory<SiteFactory> */
    use HasFactory;

    public const string DEFAULT_ACCENT = '#0f766e';

    public const string TYPE_STANDARD = 'standard';

    public const string TYPE_SWEEPSTAKES = 'sweepstakes';

    /**
     * A cap on how many sites exist at once, so a runaway caller fills a small
     * table and then stops rather than running without bound.
     */
    public const int MAX = 2_000;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'inputs' => 'array',
            'content' => 'array',
            'settings' => 'array',
            'published_at' => 'datetime',
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
     * @return HasMany<SiteEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(SiteEntry::class);
    }

    /**
     * @return HasMany<SiteStat, $this>
     */
    public function stats(): HasMany
    {
        return $this->hasMany(SiteStat::class);
    }

    /**
     * A readable, unguessable-enough public address: the business name, then
     * six random characters so two promos never collide.
     */
    public static function slugFor(string $businessName): string
    {
        $base = Str::limit(Str::slug($businessName), 48, '');

        return ($base !== '' ? $base.'-' : 'site-').Str::lower(Str::random(6));
    }

    /**
     * The slug from whatever address is passed back: a full URL, a path, or the
     * bare slug. Null when nothing slug-shaped is left.
     */
    public static function slugFromAddress(string $address): ?string
    {
        $path = (string) (parse_url(trim($address), PHP_URL_PATH) ?? trim($address));
        $slug = strtolower(trim((string) last(explode('/', trim($path, '/')))));

        return preg_match('/^[a-z0-9-]{1,72}$/', $slug) === 1 ? $slug : null;
    }

    /**
     * What an open page compares against to know it is out of date, and the
     * cache key the rendered HTML is stored under.
     */
    public function version(): string
    {
        return (string) $this->updated_at?->getTimestamp().'-'.substr(md5((string) json_encode($this->content).json_encode($this->settings)), 0, 12);
    }

    public function url(): string
    {
        return route('microsite.show', $this->slug);
    }

    public function isSweepstakes(): bool
    {
        return $this->type === self::TYPE_SWEEPSTAKES;
    }

    /**
     * Prove control of this site: the manage token on the link or in the cookie
     * hashes to the stored value.
     */
    public function managedBy(?string $token): bool
    {
        return is_string($token) && $token !== '' && hash_equals($this->manage_token_hash, hash('sha256', $token));
    }

    public function cookieName(): string
    {
        return 'mim_manage_'.$this->id;
    }

    /**
     * The validated accent colour, or the default when the stored value is not
     * a strict six-digit hex (it is checked on the way in and again here).
     */
    public function accent(): string
    {
        $accent = $this->settings['accent_color'] ?? self::DEFAULT_ACCENT;

        return preg_match('/^#[0-9a-fA-F]{6}$/', (string) $accent) === 1 ? (string) $accent : self::DEFAULT_ACCENT;
    }

    /**
     * The hero illustration, only when it is one of the closed list; never a
     * name that could reach a view path it should not.
     */
    public function hero(): ?string
    {
        $hero = $this->settings['hero_image'] ?? null;

        return in_array($hero, HeroArt::ILLUSTRATIONS, true) ? $hero : null;
    }

    public function ga4Id(): ?string
    {
        $id = $this->settings['ga4_id'] ?? null;

        return is_string($id) && preg_match('/^G-[A-Z0-9]{4,20}$/', $id) === 1 ? $id : null;
    }

    public function showsBranding(): bool
    {
        return ! (bool) ($this->settings['remove_branding'] ?? false);
    }
}
