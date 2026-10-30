<?php

namespace App\Http\Controllers;

use App\Jobs\ForwardEntry;
use App\Jobs\RecordSiteClick;
use App\Jobs\RecordSiteHit;
use App\Jobs\VerifyEntryEmail;
use App\Jobs\VerifyEntryPhone;
use App\Models\Site;
use App\Models\SiteAlarm;
use App\Models\SiteAsset;
use App\Models\SiteEntry;
use App\Sites\ClickTargets;
use App\Support\BannerImage;
use App\Support\EmailAuthenticity;
use App\Support\EntryCode;
use App\Support\LiveViewers;
use App\Support\QrCode;
use App\Support\TrafficSource;
use App\Support\Turnstile;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Serves a published microsite. Everything on the page came from the model, so
 * the template escapes every value and loads nothing from anywhere else — no
 * remote scripts, images or fonts (the one optional exception is the owner's
 * own Google Analytics tag). The accent colour is the only model value used in
 * CSS and is checked against a strict hex pattern. The rendered HTML is cached
 * by version so the page is cheap to serve at volume; the view counter runs on
 * a queue after the response, never blocking the page.
 */
class MicrositeController extends Controller
{
    public function show(Request $request, string $slug): Response
    {
        return $this->showForSite($request, Site::query()->where('slug', $slug)->firstOrFail());
    }

    /**
     * Render a site's published page — reached by its slug, or by a vanity
     * domain's Host (see SiteBuilderController::index). The HTML is cached by
     * version; the view counter runs on a queue after the response.
     */
    public function showForSite(Request $request, Site $site): Response
    {
        // Not cached: a site lapses with the clock, not with a write, so the version key cannot see it.
        if ($site->isLapsed()) {
            return response()->view('microsite.paused', ['site' => $site]);
        }

        $version = $site->version();
        $host = md5($request->getHost());
        $phase = $site->phase();

        $view = match ($phase) {
            Site::PHASE_COMING_SOON => 'microsite.coming-soon',
            Site::PHASE_ENDED => 'microsite.ended',
            default => 'microsite.show',
        };

        // Cache expires at the next scheduled transition (go-live/end) so the
        // page flips phase on time rather than serving a stale state for an hour.
        $next = $site->nextTransitionAt();
        $until = $next !== null && $next->lt(now()->addHour()) ? $next : now()->addHour();

        $html = Cache::remember(
            "site:{$site->id}:{$host}:{$phase}:{$version}",
            $until,
            fn (): string => view($view, $this->viewData($site))->render(),
        );

        dispatch(RecordSiteHit::forNow($site->id, TrafficSource::fromRequest($request)))->afterResponse();

        return response($html)->withHeaders([
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'public, max-age=60',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'X-Frame-Options' => 'SAMEORIGIN',
        ]);
    }

    /**
     * What an open page polls to know when to reload itself: an opaque stamp
     * that changes whenever the owner regenerates or edits the page.
     */
    public function version(Request $request, string $slug): JsonResponse
    {
        $site = Site::query()->where('slug', $slug)->firstOrFail();

        // The page polls this every few seconds; use it as the presence beat.
        if (($vid = (string) $request->query('v', '')) !== '') {
            LiveViewers::beat($site->id, $vid);
        }

        return response()->json(['version' => $site->version()])->header('Cache-Control', 'no-store');
    }

    /**
     * A time-on-page beacon: the page sends how many seconds it was actively
     * visible when the visitor leaves. CSRF-exempt and capped, like the entry
     * endpoint. Kept as a running total + count so the average can be shown.
     */
    public function ping(Request $request, string $slug): Response
    {
        $site = Site::query()->where('slug', $slug)->firstOrFail();

        $seconds = (int) round((float) $request->input('seconds', 0));

        if ($seconds >= 1 && $seconds <= 1800) {
            Site::withoutTimestamps(function () use ($site, $seconds): void {
                Site::query()->whereKey($site->id)->increment('dwell_ms_total', $seconds * 1000);
                Site::query()->whereKey($site->id)->increment('dwell_count');
            });
        }

        return response()->noContent();
    }

    /**
     * Ingest one click on a tracked link or button (the Pro click-tracking
     * add-on). The published page's beacon posts a stable element id; this
     * endpoint is public and CSRF-exempt like the other microsite beacons, and
     * is rate-limited per IP by the route throttle. Everything counted is
     * anonymous — no cookie, no visitor identity. A click that does not pass the
     * gates is accepted and silently dropped, so a beacon never errors a page.
     */
    public function track(Request $request, string $slug): Response
    {
        $site = Site::query()->where('slug', $slug)->firstOrFail();

        // Only a Pro site with the add-on is counted; otherwise drop silently.
        if (! $site->tracksClicks()) {
            return response()->noContent();
        }

        // Drop obvious bots so counts aren't trivially inflated by crawlers.
        if (self::looksLikeBot((string) $request->userAgent())) {
            return response()->noContent();
        }

        // Only a known element id for this site is counted. An unknown id
        // (spoofed or stale) is dropped, which bounds what can ever reach the
        // roll-up — the element set is fixed and assigned at publish time.
        $elementId = $this->stringInput($request, 'id');
        $slot = ClickTargets::slotForId($site->trackIds(), $elementId);

        if ($slot === null) {
            return response()->noContent();
        }

        // The href is resolved server-side from the slot, never taken from the
        // client, so the beacon can't smuggle arbitrary strings into storage.
        dispatch(RecordSiteClick::forNow($site->id, $elementId, $this->trackedHref($site, $slot)))->afterResponse();

        return response()->noContent();
    }

    /**
     * The href the page renders for a tracked slot, resolved server-side. Only
     * link slots have one; buttons return null.
     */
    protected function trackedHref(Site $site, string $slot): ?string
    {
        return match ($slot) {
            'hero_cta' => '#enter',
            'rules' => route('microsite.rules', $site->slug, absolute: false),
            'rules_doc' => $site->asset(SiteAsset::RULES_DOC) !== null
                ? route('microsite.asset', [$site->slug, SiteAsset::RULES_DOC], absolute: false)
                : null,
            'branding' => route('home', absolute: false),
            default => null,
        };
    }

    /**
     * Whether a user agent is an obvious bot or missing entirely. A real
     * browser's sendBeacon always carries a UA, so an empty one is treated as a
     * bot. Deliberately simple for v1 — a coarse filter, not a bot-detection
     * system.
     */
    protected static function looksLikeBot(string $userAgent): bool
    {
        if (trim($userAgent) === '') {
            return true;
        }

        return preg_match(
            '/bot|crawl|spider|slurp|baidu|yandex|duckduck|facebookexternalhit|embedly|preview|headless|phantom|curl|wget|python-requests|http-client|monitor/i',
            $userAgent,
        ) === 1;
    }

    /**
     * A minimal, frameable form-only page for embedding the entry form on
     * another site. It posts to the same entry endpoint. Explicitly frameable.
     */
    public function embed(string $slug): Response
    {
        $site = Site::query()->where('slug', $slug)->firstOrFail();

        $html = view('microsite.embed', [
            'content' => $site->content,
            'accent' => $site->accent(),
            'requiresEmailCode' => $site->requiresEmailCode(),
            'collectsDob' => $site->collectsDateOfBirth(),
            'fullUrl' => $site->url(),
            'client' => ['enter' => route('microsite.enter', $site->slug, absolute: true)],
        ])->render();

        return response($html)->withHeaders([
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Security-Policy' => 'frame-ancestors *',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    public function rules(string $slug): View
    {
        $site = Site::query()->where('slug', $slug)->firstOrFail();

        abort_unless($site->isSweepstakes() && is_array($site->content['rules'] ?? null), 404);

        return view('microsite.rules', [
            'site' => $site,
            'content' => $site->content,
            'rules' => $site->content['rules'],
            'accent' => $site->accent(),
        ]);
    }

    /**
     * Capture a lead or sweepstakes entry. The form posts with fetch and this
     * returns JSON, so the public page stays fully cacheable and needs no
     * session. The route is CSRF-exempt (there is no session to protect) and
     * guarded instead by a honeypot field, a per-IP rate limit, and strict
     * validation. A filled honeypot is treated as success without storing
     * anything, so a bot learns nothing.
     */
    public function enter(Request $request, string $slug): JsonResponse
    {
        $site = Site::query()->where('slug', $slug)->firstOrFail();

        $successMessage = $site->content['form']['success'] ?? 'Thanks — you are all set.';

        if (filled($request->input('website'))) {
            return response()->json(['ok' => true, 'message' => $successMessage]);
        }

        // A "notify me" signup on a coming-soon page is allowed as a waitlist
        // lead even though the page isn't live yet.
        $waitlist = $site->phase() === Site::PHASE_COMING_SOON && $request->boolean('waitlist');

        if (! $site->acceptsEntries() && ! $waitlist) {
            $message = $site->phase() === Site::PHASE_ENDED
                ? 'This promotion has ended.'
                : ($site->isDraft()
                    ? 'This page is a draft and is not accepting entries yet.'
                    : 'This promotion is not currently accepting entries.');

            return response()->json(['ok' => false, 'message' => $message], 422);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        // Optional date-of-birth add-on. Validated up front — before the one-time
        // email code is burned or a photo is written to disk — so a bad date
        // never costs the entrant their code or leaves an orphan upload. Stored
        // in the entry's extra JSON, so no schema change is needed and future
        // optional fields can sit beside it. The field is a native date input, so
        // the format is fixed; today back to 1900 is accepted.
        $extra = [];
        if ($site->collectsDateOfBirth()) {
            $dob = $request->validate([
                'dob' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today', 'after_or_equal:1900-01-01'],
            ], [], ['dob' => 'date of birth'])['dob'] ?? null;

            if (filled($dob)) {
                $extra['dob'] = $dob;
            }
        }

        // Email code verification add-on: every real entry on a site that
        // turned it on must carry a verified Turnstile token (when configured)
        // and an email proven by a one-time code. This runs before the entry is
        // created AND before the duplicate-email check, so the gate can't be
        // skipped with a phone-only post or by probing a known address. The
        // code is burned on success (single-use). Waitlist signups on a
        // coming-soon page are exempt — that form has no code step.
        if ($site->requiresEmailCode()) {
            // Turnstile guards every entry on the site, waitlist included, so no
            // path into the form skips the bot check.
            if (! Turnstile::verify($this->stringInput($request, 'cf-turnstile-response'), $request->ip(), $request->getHost())) {
                return response()->json(['ok' => false, 'message' => 'Please complete the verification and try again.'], 422);
            }

            // The email + one-time code step applies to real entries only; a
            // pre-launch waitlist signup has no code step on its page.
            if (! $waitlist) {
                if (blank($validated['email'] ?? null)) {
                    return response()->json([
                        'ok' => false,
                        'errors' => ['email' => ['Please enter your email so we can verify it with a code.']],
                    ], 422);
                }

                if (! EntryCode::check($site->id, $validated['email'], $this->stringInput($request, 'code'))) {
                    return response()->json([
                        'ok' => false,
                        'errors' => ['code' => ['That code is incorrect or has expired — request a new one.']],
                    ], 422);
                }
            }
        }

        // One entry per person: a repeat with the same email is accepted
        // politely but not stored or counted again.
        if (filled($validated['email'] ?? null)
            && $site->entries()->whereRaw('lower(email) = ?', [mb_strtolower($validated['email'])])->exists()) {
            return response()->json(['ok' => true, 'message' => "You're already entered — good luck!"]);
        }

        if (blank($validated['email'] ?? null) && blank($validated['phone'] ?? null)) {
            return response()->json([
                'ok' => false,
                'errors' => ['email' => ['Please give an email or a phone number so you can be reached.']],
            ], 422);
        }

        $dims = TrafficSource::fromInput($request);

        // The confirmation add-on (double opt-in): hold the entry unconfirmed
        // behind an emailed link so only authenticated leads count.
        $needsConfirmation = ($site->settings['require_confirmation'] ?? false) && filled($validated['email'] ?? null);
        $confirmToken = $needsConfirmation ? Str::random(40) : null;

        // Optional photo (e.g. "submit your smile"). Only when the site asks for
        // one, and never stored as sent: the upload is validated and redrawn
        // through GD, which drops EXIF and anything hiding in the file, then
        // kept on the private assets disk under an unguessable name. Served back
        // only to the owner through an authorised route.
        $photoPath = null;
        if ($site->collectsPhotos() && $request->hasFile('photo')) {
            $request->validate(['photo' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:12288']]);
            $photoPath = $site->assetFolder().'/entries/'.Str::random(32).'.jpg';
            Storage::disk(SiteAsset::DISK)->put($photoPath, BannerImage::redraw($request->file('photo'), 'photo', 1600));
        }

        $entry = SiteEntry::query()->create([
            'site_id' => $site->id,
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'message' => $validated['message'] ?? null,
            'photo_path' => $photoPath,
            'extra' => $extra ?: null,
            'is_amoe' => false,
            'ip_hash' => hash('sha256', (string) $request->ip()),
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 490, ''),
            'country' => $this->country($request),
            'source' => $waitlist ? 'waitlist' : $dims['source'],
            'campaign' => $dims['campaign'],
            'device' => $dims['device'],
            'confirm_token' => $confirmToken,
            'confirmed_at' => $needsConfirmation ? null : now(),
        ]);

        Site::withoutTimestamps(fn () => $site->increment('entry_count'));

        // Analytics roll-ups must never sink a successful entry: the entry is
        // already saved, so a stats write that fails (e.g. a DB hiccup) is logged
        // and swallowed rather than 500-ing the visitor's submission.
        try {
            RecordSiteHit::record($site->id, 'entries', now());
            RecordSiteHit::breakdowns($site->id, $dims, 'entries');
        } catch (\Throwable $e) {
            Log::warning('Entry stats recording failed', ['site' => $site->id, 'error' => $e->getMessage()]);
        }

        SiteAlarm::checkFor($site->refresh(), [SiteAlarm::ENTRIES]);

        // Verification add-ons, off the request path, when the owner opted in.
        if (($site->settings['verify_emails'] ?? false) && filled($entry->email)) {
            VerifyEntryEmail::dispatch($entry->id);
        }

        if (($site->settings['verify_phones'] ?? false) && filled($entry->phone)) {
            VerifyEntryPhone::dispatch($entry->id);
        }

        if (filled($site->settings['webhook_url'] ?? null)) {
            ForwardEntry::dispatch($entry->id);
        }

        if ($needsConfirmation) {
            $this->sendConfirmation($site, $entry, $confirmToken);

            return response()->json(['ok' => true, 'message' => 'Almost there — check your email and click the link to confirm your entry.']);
        }

        return response()->json(['ok' => true, 'message' => $successMessage]);
    }

    /**
     * Confirm an entry from the emailed double-opt-in link.
     */
    public function confirm(string $slug, string $token): View
    {
        $site = Site::query()->where('slug', $slug)->firstOrFail();

        $entry = $site->entries()->where('confirm_token', $token)->first();

        if ($entry !== null && $entry->confirmed_at === null) {
            $entry->update(['confirmed_at' => now(), 'confirm_token' => null]);
        }

        return view('microsite.confirmed', [
            'site' => $site,
            'accent' => $site->accent(),
            'confirmed' => $entry !== null,
        ]);
    }

    /**
     * Email the double-opt-in confirmation link. Uses the configured mailer
     * (log locally); failures never block the entry.
     */
    protected function sendConfirmation(Site $site, SiteEntry $entry, string $token): void
    {
        $url = route('microsite.confirm', [$site->slug, $token], absolute: true);
        $name = $site->inputs['business_name'] ?? 'your entry';

        try {
            Mail::raw(
                "Thanks for entering {$name}.\n\nPlease confirm your entry by opening this link:\n{$url}\n\nIf this wasn't you, just ignore this email.",
                function ($message) use ($entry, $name): void {
                    $message->to($entry->email)->subject("Confirm your entry — {$name}");
                },
            );
        } catch (\Throwable $e) {
            Log::warning('Confirmation email failed', ['entry' => $entry->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Issue a one-time 6-digit email verification code for an entry. Guarded by
     * the honeypot, a Turnstile token (when configured), a confirm-email match
     * and a per-email-and-IP rate limit. The code is emailed and never returned
     * or logged. Used only by sites that turned the add-on on.
     */
    public function sendCode(Request $request, string $slug): JsonResponse
    {
        $site = Site::query()->where('slug', $slug)->firstOrFail();

        abort_unless($site->requiresEmailCode(), 404);

        // A filled honeypot looks like success but sends nothing.
        if (filled($request->input('website'))) {
            return response()->json(['ok' => true, 'retry_after' => EntryCode::RESEND_SECONDS]);
        }

        if (! Turnstile::verify($this->stringInput($request, 'cf-turnstile-response'), $request->ip(), $request->getHost())) {
            return response()->json(['ok' => false, 'message' => 'Please complete the verification and try again.'], 422);
        }

        // Compare the two addresses case-insensitively (as the client does) and
        // lower-case the email the code is keyed to, so Jamie@x and jamie@x agree.
        $request->merge([
            'email' => is_string($request->input('email')) ? mb_strtolower(trim($request->input('email'))) : $request->input('email'),
            'email_confirm' => is_string($request->input('email_confirm')) ? mb_strtolower(trim($request->input('email_confirm'))) : $request->input('email_confirm'),
        ]);

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:120'],
            'email_confirm' => ['required', 'same:email'],
        ], ['email_confirm.same' => 'The two email addresses do not match.']);

        $email = $validated['email'];

        // Rate limit per email + IP before the checks below, so the DNS lookup
        // among them runs only a capped number of times and send-code can't be
        // hammered to probe. Every processed request counts, not just sends.
        $limiterKey = 'sendcode:'.$site->id.':'.sha1($email.'|'.(string) $request->ip());

        if (RateLimiter::tooManyAttempts($limiterKey, 5)) {
            return response()->json([
                'ok' => false,
                'message' => 'Too many codes requested. Please wait a few minutes and try again.',
                'retry_after' => RateLimiter::availableIn($limiterKey),
            ], 429);
        }

        RateLimiter::hit($limiterKey, 600);

        // Cheap authenticity checks before spending a send (the cost-ordered
        // funnel: Turnstile, then these free checks, then the email). These are
        // domain-level reasons, safe to show the visitor.
        if (EmailAuthenticity::isDisposable($email)) {
            return response()->json([
                'ok' => false,
                'message' => 'Please use a non-disposable email address.',
                'errors' => ['email' => ['Please use a non-disposable email address.']],
            ], 422);
        }

        if (! EmailAuthenticity::hasMailServer($email)) {
            return response()->json([
                'ok' => false,
                'message' => "That email domain can't receive mail — please check the address.",
                'errors' => ['email' => ["That email domain can't receive mail — please check the address."]],
            ], 422);
        }

        // Already entered: don't send another code. Reply exactly as the normal
        // path does, so the response can't reveal who has entered, and email the
        // entrant a short note instead of a code.
        if ($site->entries()->whereRaw('lower(email) = ?', [$email])->exists()) {
            $this->sendAlreadyEnteredEmail($site, $email);

            return response()->json(['ok' => true, 'retry_after' => EntryCode::RESEND_SECONDS]);
        }

        $code = EntryCode::issue($site->id, $email);

        // A code was sent moments ago: don't send another, just report the wait.
        if ($code === null) {
            return response()->json(['ok' => true, 'retry_after' => EntryCode::retryAfter($site->id, $email)]);
        }

        $this->sendCodeEmail($site, $email, $code);

        return response()->json(['ok' => true, 'retry_after' => EntryCode::RESEND_SECONDS]);
    }

    /**
     * Email a one-time code. Kept minimal for deliverability; the code is never
     * logged. A send failure never blocks — the visitor can request another.
     */
    protected function sendCodeEmail(Site $site, string $email, string $code): void
    {
        $name = $site->inputs['business_name'] ?? 'your entry';
        $from = $site->verificationSender();

        try {
            Mail::raw(
                "Your verification code for {$name} is: {$code}\n\n"
                .'It expires in '.EntryCode::TTL_MINUTES." minutes. If you didn't request this, you can ignore this email.",
                function ($message) use ($email, $name, $from): void {
                    $message->from($from['address'], $from['name'])->to($email)->subject("Your verification code — {$name}");
                },
            );
        } catch (\Throwable $e) {
            Log::warning('Entry code email failed', ['site' => $site->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Tell an already-entered address it is in, instead of a code. Sent so the
     * send-code reply can stay identical for entered and new addresses (no
     * membership oracle); a failure never blocks.
     */
    protected function sendAlreadyEnteredEmail(Site $site, string $email): void
    {
        $name = $site->inputs['business_name'] ?? 'your entry';
        $from = $site->verificationSender();

        try {
            Mail::raw(
                "Good news — you're already entered for {$name}.\n\n"
                .'There is no need to sign up again, so you can ignore the code prompt. Good luck!',
                function ($message) use ($email, $name, $from): void {
                    $message->from($from['address'], $from['name'])->to($email)->subject("You're already entered — {$name}");
                },
            );
        } catch (\Throwable $e) {
            Log::warning('Already-entered email failed', ['site' => $site->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Read a request field as a string, treating an array or missing value as
     * an empty string. Guards against array inputs (e.g. code[]=x) crashing a
     * string cast or a ?string argument on these public endpoints.
     */
    protected function stringInput(Request $request, string $key): string
    {
        $value = $request->input($key);

        return is_string($value) ? $value : '';
    }

    /**
     * The visitor's country from Cloudflare's CF-IPCountry header (set in
     * production behind Cloudflare), or null. "XX"/"T1" mean unknown/Tor.
     */
    protected function country(Request $request): ?string
    {
        $country = strtoupper((string) $request->headers->get('CF-IPCountry'));

        return preg_match('/^[A-Z]{2}$/', $country) === 1 && ! in_array($country, ['XX', 'T1'], true)
            ? $country
            : null;
    }

    /**
     * Banner image URLs (cache-busted by the asset's timestamp), or null.
     *
     * @return array{desktop: ?string, mobile: ?string}
     */
    protected function bannerUrls(Site $site): array
    {
        $url = function (string $slot) use ($site): ?string {
            $asset = $site->asset($slot);

            return $asset !== null
                ? route('microsite.asset', [$site->slug, $slot], absolute: false).'?v='.$asset->updated_at?->getTimestamp()
                : null;
        };

        return [
            'desktop' => $url(SiteAsset::BANNER_DESKTOP),
            'mobile' => $url(SiteAsset::BANNER_MOBILE),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function viewData(Site $site): array
    {
        return [
            'site' => $site,
            'content' => $site->content,
            'accent' => $site->accent(),
            'hero' => $site->hero(),
            'ga4' => $site->ga4Id(),
            'version' => $site->version(),
            'qr' => QrCode::svg($site->url()),
            'showBranding' => $site->showsBranding(),
            'isDraft' => $site->isDraft(),
            'acceptsEntries' => $site->acceptsEntries(),
            'phase' => $site->phase(),
            'goLiveAt' => $site->goLiveAt(),
            'endsAt' => $site->endsAt(),
            'banners' => $this->bannerUrls($site),
            'rulesDocUrl' => $site->asset(SiteAsset::RULES_DOC) !== null
                ? route('microsite.asset', [$site->slug, SiteAsset::RULES_DOC], absolute: false)
                : null,
            'turnstileSiteKey' => Turnstile::siteKey(),
            // Click tracking renders only for a Pro site with the add-on: the
            // elements carry their ids and the beacon has an endpoint to post
            // to; otherwise both are empty and the page tracks nothing.
            'trackIds' => $site->tracksClicks() ? $site->trackIds() : [],
            'client' => [
                'version' => $site->version(),
                'enter' => route('microsite.enter', $site->slug, absolute: false),
                'send_code' => route('microsite.send-code', $site->slug, absolute: false),
                'require_code' => $site->requiresEmailCode(),
                'resend_seconds' => EntryCode::RESEND_SECONDS,
                'version_url' => route('microsite.version', $site->slug, absolute: false),
                'ping' => route('microsite.ping', $site->slug, absolute: false),
                'track' => $site->tracksClicks() ? route('microsite.track', $site->slug, absolute: false) : null,
            ],
        ];
    }
}
