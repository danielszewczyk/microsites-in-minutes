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

    public const string TYPE_EVENT = 'event';

    /**
     * The kinds of microsite the builder offers. Keyed by type; each has a
     * short label and the lead-capture verb. Add a kind here and in
     * TemplateCopywriter to extend the product beyond these.
     *
     * @var array<string, array{label: string, verb: string}>
     */
    public const array TYPES = [
        self::TYPE_STANDARD => ['label' => 'Promo / landing page', 'verb' => 'Get in touch'],
        self::TYPE_EVENT => ['label' => 'Event', 'verb' => 'RSVP'],
        self::TYPE_SWEEPSTAKES => ['label' => 'Sweepstakes / giveaway', 'verb' => 'Enter'],
    ];

    public const string STATUS_DRAFT = 'draft';

    public const string STATUS_PUBLISHED = 'published';

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
     * @return HasMany<SiteAsset, $this>
     */
    public function assets(): HasMany
    {
        return $this->hasMany(SiteAsset::class);
    }

    public function asset(string $slot): ?SiteAsset
    {
        return $this->assets->firstWhere('slot', $slot);
    }

    /**
     * @return HasMany<SiteBreakdown, $this>
     */
    public function breakdowns(): HasMany
    {
        return $this->hasMany(SiteBreakdown::class);
    }

    /**
     * @return HasMany<Domain, $this>
     */
    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }

    public function domain(): ?Domain
    {
        return $this->domains()->latest('id')->first();
    }

    /**
     * @return HasMany<ShortLink, $this>
     */
    public function shortLinks(): HasMany
    {
        return $this->hasMany(ShortLink::class);
    }

    /**
     * @return HasMany<SiteAlarm, $this>
     */
    public function alarms(): HasMany
    {
        return $this->hasMany(SiteAlarm::class);
    }

    public function assetFolder(): string
    {
        return 'sites/'.$this->slug;
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
        // Based only on what the owner can change (content, settings, status,
        // uploaded assets) — never on updated_at or the view/entry counters, so
        // a visit never changes the version (which would loop the self-refresh
        // and bust the HTML cache on every view).
        $assets = $this->exists ? $this->assets()->pluck('updated_at', 'slot')->toJson() : '';

        return substr(md5($this->status.'|'.json_encode($this->content).'|'.json_encode($this->settings).'|'.$assets), 0, 16);
    }

    public function url(): string
    {
        return route('microsite.show', $this->slug);
    }

    public function isSweepstakes(): bool
    {
        return $this->type === self::TYPE_SWEEPSTAKES;
    }

    public function isEvent(): bool
    {
        return $this->type === self::TYPE_EVENT;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Whether this site is taking entries right now: it must be published, and
     * a sweepstakes must be inside its entry window when dates are set.
     */
    public function acceptsEntries(): bool
    {
        if ($this->isDraft()) {
            return false;
        }

        $start = $this->inputDate('start_date');
        $end = $this->inputDate('end_date');

        if ($start !== null && now()->lt($start)) {
            return false;
        }

        if ($end !== null && now()->gt($end->endOfDay())) {
            return false;
        }

        return true;
    }

    /**
     * A date from the stored inputs, or null when absent or unparseable.
     */
    public function inputDate(string $key): ?Carbon
    {
        $value = $this->inputs[$key] ?? null;

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
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

    /**
     * Average time a visitor actively spent on the page, in whole seconds.
     */
    public function avgDwellSeconds(): int
    {
        return $this->dwell_count > 0 ? (int) round($this->dwell_ms_total / $this->dwell_count / 1000) : 0;
    }
}
