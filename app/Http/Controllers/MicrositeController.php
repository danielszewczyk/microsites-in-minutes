<?php

namespace App\Http\Controllers;

use App\Jobs\ForwardEntry;
use App\Jobs\RecordSiteHit;
use App\Jobs\VerifyEntryEmail;
use App\Jobs\VerifyEntryPhone;
use App\Models\Site;
use App\Models\SiteAlarm;
use App\Models\SiteAsset;
use App\Models\SiteEntry;
use App\Support\BannerImage;
use App\Support\QrCode;
use App\Support\TrafficSource;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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

        dispatch(RecordSiteHit::forToday($site->id, TrafficSource::fromRequest($request)))->afterResponse();

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
    public function version(string $slug): JsonResponse
    {
        $site = Site::query()->where('slug', $slug)->firstOrFail();

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
     * A minimal, frameable form-only page for embedding the entry form on
     * another site. It posts to the same entry endpoint. Explicitly frameable.
     */
    public function embed(string $slug): Response
    {
        $site = Site::query()->where('slug', $slug)->firstOrFail();

        $html = view('microsite.embed', [
            'content' => $site->content,
            'accent' => $site->accent(),
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

        $day = Carbon::today()->toDateString();
        RecordSiteHit::bump($site->id, $day, 'entries');
        RecordSiteHit::breakdowns($site->id, $dims, 'entries');

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
            'client' => [
                'version' => $site->version(),
                'enter' => route('microsite.enter', $site->slug, absolute: false),
                'version_url' => route('microsite.version', $site->slug, absolute: false),
                'ping' => route('microsite.ping', $site->slug, absolute: false),
            ],
        ];
    }
}
