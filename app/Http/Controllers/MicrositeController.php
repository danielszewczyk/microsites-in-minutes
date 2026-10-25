<?php

namespace App\Http\Controllers;

use App\Jobs\RecordSiteHit;
use App\Models\Site;
use App\Models\SiteAsset;
use App\Models\SiteEntry;
use App\Support\QrCode;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

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
        $site = Site::query()->where('slug', $slug)->firstOrFail();

        $version = $site->version();

        $html = Cache::remember(
            "site:{$site->id}:{$version}",
            now()->addHour(),
            fn (): string => view('microsite.show', $this->viewData($site))->render(),
        );

        dispatch(RecordSiteHit::forToday($site->id))->afterResponse();

        return response($html)->withHeaders([
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'public, max-age=60',
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

        if (! $site->acceptsEntries()) {
            $message = $site->isDraft()
                ? 'This page is a draft and is not accepting entries yet.'
                : 'This promotion is not currently accepting entries.';

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

        SiteEntry::query()->create([
            'site_id' => $site->id,
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'message' => $validated['message'] ?? null,
            'is_amoe' => false,
            'ip_hash' => hash('sha256', (string) $request->ip()),
        ]);

        $site->increment('entry_count');
        RecordSiteHit::bump($site->id, Carbon::today()->toDateString(), 'entries');

        return response()->json(['ok' => true, 'message' => $successMessage]);
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
            'banners' => $this->bannerUrls($site),
            'rulesDocUrl' => $site->asset(SiteAsset::RULES_DOC) !== null
                ? route('microsite.asset', [$site->slug, SiteAsset::RULES_DOC], absolute: false)
                : null,
            'client' => [
                'version' => $site->version(),
                'enter' => route('microsite.enter', $site->slug, absolute: false),
                'version_url' => route('microsite.version', $site->slug, absolute: false),
            ],
        ];
    }
}
