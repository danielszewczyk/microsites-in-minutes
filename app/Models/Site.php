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
            'paid_through' => 'datetime',
        ];
    }

    /**
     * Whether the site has paid time left. Only a site that has ever been paid
     * for is tracked; a site with no paid_through is outside the paywall.
     */
    public function isPaid(): bool
    {
        return $this->paid_through !== null && $this->paid_through->isFuture();
    }

    /**
     * A paid site whose time has run out: it is paused until the owner pays again.
     */
    public function isLapsed(): bool
    {
        return (bool) config('plans.paywall') && $this->paid_through !== null && $this->paid_through->isPast();
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
     * @return HasMany<SiteClickEvent, $this>
     */
    public function clickEvents(): HasMany
    {
        return $this->hasMany(SiteClickEvent::class);
    }

    /**
     * @return HasMany<SiteClickStat, $this>
     */
    public function clickStats(): HasMany
    {
        return $this->hasMany(SiteClickStat::class);
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

    public const string PHASE_COMING_SOON = 'coming_soon';

    public const string PHASE_LIVE = 'live';

    public const string PHASE_ENDED = 'ended';

    public function goLiveAt(): ?Carbon
    {
        return $this->settingDate('go_live_at');
    }

    public function endsAt(): ?Carbon
    {
        return $this->settingDate('ends_at');
    }

    /**
     * Which page the public sees right now: a coming-soon page before the
     * go-live trigger, an ended page after the end trigger, otherwise the live
     * page. A draft is always treated as coming-soon.
     */
    public function phase(): string
    {
        $goLive = $this->goLiveAt();
        $ends = $this->endsAt();

        if ($goLive !== null && now()->lt($goLive)) {
            return self::PHASE_COMING_SOON;
        }

        if ($ends !== null && now()->gt($ends)) {
            return self::PHASE_ENDED;
        }

        return self::PHASE_LIVE;
    }

    /**
     * When the page next changes phase on its own (go-live or end), so the
     * cached render can expire exactly then rather than showing a stale state.
     */
    public function nextTransitionAt(): ?Carbon
    {
        $upcoming = collect([$this->goLiveAt(), $this->endsAt()])
            ->filter(fn (?Carbon $at): bool => $at !== null && $at->isFuture())
            ->sort()
            ->first();

        return $upcoming;
    }

    protected function settingDate(string $key): ?Carbon
    {
        $value = $this->settings[$key] ?? null;

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
     * Whether this site is taking entries right now: it must be published, and
     * a sweepstakes must be inside its entry window when dates are set.
     */
    public function acceptsEntries(): bool
    {
        if ($this->isDraft() || $this->isLapsed() || $this->phase() !== self::PHASE_LIVE) {
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

    /**
     * Whether the hero text sits over the banner image rather than below it.
     */
    public function heroOverlay(): bool
    {
        return ($this->settings['hero_overlay'] ?? false) === true;
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
     * Whether the entry form invites the visitor to add a photo (e.g. a
     * "submit your smile" promo). Mobile capture only; off by default.
     */
    public function collectsPhotos(): bool
    {
        return (bool) ($this->settings['collect_photo'] ?? false);
    }

    /**
     * Whether email entries must be verified with a one-time 6-digit code sent
     * to the address before the entry is accepted. Off by default.
     */
    public function requiresEmailCode(): bool
    {
        return (bool) ($this->settings['require_email_code'] ?? false);
    }

    /**
     * The from-address and name for this site's verification emails. Defaults
     * to the platform's own Resend-verified domain; a per-site override (the
     * seam for future per-customer verified domains) lives in
     * settings['verification_from'] as {address, name} and is read here so the
     * sending domain is never hard-coded at the call site.
     *
     * @return array{address: string, name: string}
     */
    public function verificationSender(): array
    {
        $override = is_array($this->settings['verification_from'] ?? null) ? $this->settings['verification_from'] : [];

        return [
            'address' => $override['address'] ?? config('services.verification.from_address'),
            'name' => $override['name'] ?? ($this->inputs['business_name'] ?? config('services.verification.from_name')),
        ];
    }

    /**
     * Whether this site's owner is entitled to the Pro click-tracking add-on.
     * Gated on a plan feature flag (config/plans.php), never hard-coded to a
     * tier, so how it is packaged can change without touching this check.
     */
    public function tracksClicks(): bool
    {
        return $this->owner->allows('click_tracking');
    }

    /**
     * The slot → stable element-id map assigned to this site's trackable links
     * and buttons at publish time. Empty for a site published before the add-on
     * existed; such a site starts tracking once it is next saved.
     *
     * @return array<string, string>
     */
    public function trackIds(): array
    {
        $map = $this->content['track'] ?? [];

        return is_array($map) ? $map : [];
    }

    /**
     * Average time a visitor actively spent on the page, in whole seconds.
     */
    public function avgDwellSeconds(): int
    {
        return $this->dwell_count > 0 ? (int) round($this->dwell_ms_total / $this->dwell_count / 1000) : 0;
    }
}
