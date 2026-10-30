<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesSite;
use App\Models\Site;
use App\Models\Site as SiteModel;
use App\Models\SiteAsset;
use App\Models\SiteBreakdown;
use App\Models\SiteHourlyStat;
use App\Models\SiteMinuteStat;
use App\Models\SiteStat;
use App\Sites\Billing;
use App\Sites\ClickTargets;
use App\Sites\CopyEdits;
use App\Sites\HeroArt;
use App\Sites\Plans;
use App\Sites\SiteComposer;
use App\Sites\UsageReport;
use App\Support\LiveViewers;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Stripe\Exception\ApiErrorException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The owner's dashboard for one microsite: edit the prompt and regenerate in
 * place, change settings, upload assets, and see and export entries and stats.
 * Control is proven by the manage token — held in a cookie, or passed once as
 * ?t= on the share link — not by a login.
 */
class SiteManageController extends Controller
{
    use ManagesSite;

    /** The chart ranges the dashboard offers, each mapping to an analytics tier. */
    public const array RANGES = ['hour', 'day', 'week', 'month', 'year', 'all'];

    /**
     * Does this browser hold a valid manage token for the site? Never cached:
     * the draft page is served from cache to everyone, so it asks this to
     * decide whether to show the owner's Manage link.
     */
    public function check(Request $request, string $slug): Response
    {
        $site = Site::query()->where('slug', $slug)->firstOrFail();
        $this->authorizeManage($request, $site);

        return response()->noContent()->header('Cache-Control', 'no-store, private');
    }

    public function manage(Request $request, string $slug): RedirectResponse|View
    {
        $site = Site::query()->where('slug', $slug)->firstOrFail();

        // The share link carries the token once; store it in a cookie and drop
        // it from the address so it is not left in history or bookmarks shown.
        if ($request->filled('t') && $site->managedBy($request->query('t'))) {
            return redirect()
                ->route('microsite.manage', $site->slug)
                ->withCookie(cookie($site->cookieName(), (string) $request->query('t'), 60 * 24 * 30));
        }

        $this->authorizeManage($request, $site);

        // The cookie holds the plain token; use it to build the phone-upload QR.
        $token = (string) $request->cookie($site->cookieName());

        return view('manage.index', [
            'site' => $site,
            'inputs' => $site->inputs,
            'illustrations' => HeroArt::ILLUSTRATIONS,
            'entries' => $site->entries()->latest()->limit(100)->get(),
            'stats' => $this->series($site, 'month'),
            'statRange' => 'month',
            'statCaption' => $this->rangeCaption('month'),
            'live' => LiveViewers::count($site->id),
            'plan' => $site->owner->plan(),
            'launchFree' => Plans::launchFree(),
            'usage' => UsageReport::for($site->owner),
            'breakdowns' => $this->breakdowns($site),
            'tracksClicks' => $site->tracksClicks(),
            'clicks' => $this->clickReport($site),
            'domain' => $site->domain(),
            'og' => [
                'image' => route('microsite.og', $site->slug).'?v='.substr($site->version(), -8),
                'title' => $site->content['meta']['title'] ?? ($site->renderedContent()['hero']['headline'] ?? ''),
                'description' => $site->content['meta']['description'] ?? '',
                'host' => ($d = $site->domain()) && $d->isActive() ? $d->domain : parse_url($site->url(), PHP_URL_HOST),
            ],
            'links' => $site->shortLinks()->latest()->get(),
            'alarms' => $site->alarms()->get(),
            'assets' => $site->assets()->get()->keyBy('slot'),
            'uploadQr' => SiteAssetController::qrFor($site, $token),
            'uploadUrl' => route('microsite.upload', $site->slug).'?t='.$token,
            'manageToken' => $token,
        ]);
    }

    /**
     * Flip a draft to published (still on its own URL — not a real domain).
     */
    public function publish(Request $request, string $slug): RedirectResponse
    {
        $site = Site::query()->where('slug', $slug)->firstOrFail();
        $this->authorizeManage($request, $site);

        if (config('plans.paywall') && ! $site->isPaid()) {
            return back()->with('error', 'Keep it live for $1 a day to publish.');
        }

        if ($site->isDraft()) {
            $site->update(['status' => Site::STATUS_PUBLISHED, 'published_at' => $site->published_at ?? now()]);
        }

        return back()->with('status', 'Your site is now live and accepting entries.');
    }

    /**
     * Send the owner to Stripe's hosted Checkout for a day pass or the monthly plan.
     */
    public function checkout(Request $request, string $slug, string $plan, Billing $billing): RedirectResponse
    {
        $site = Site::query()->where('slug', $slug)->firstOrFail();
        $this->authorizeManage($request, $site);

        if (! config('services.stripe.enabled')) {
            return back()->with('error', 'Payments are not switched on yet.');
        }

        $owner = $site->owner;

        if ($owner === null) {
            return back()->with('error', 'Sign in to your account to pay for this site.');
        }

        // One subscription per site: a second monthly checkout would start a
        // second Stripe subscription that bills forever with no site to credit.
        if ($plan === 'monthly' && ! empty($site->settings['stripe_subscription_id'])) {
            return back()->with('error', 'This site already has a monthly subscription.');
        }

        $returnUrl = route('microsite.manage', $site->slug);

        try {
            $url = $plan === 'monthly'
                ? $billing->monthlyCheckoutUrl($site, $owner, $returnUrl)
                : $billing->dayPassCheckoutUrl($site, $owner, $returnUrl);
        } catch (ApiErrorException $e) {
            Log::error('Stripe checkout failed', ['site_id' => $site->id, 'plan' => $plan, 'message' => $e->getMessage()]);

            return back()->with('error', 'We could not reach our payment provider. Please try again in a moment.');
        }

        return redirect()->away($url);
    }

    /**
     * One-tap extension for a day, charged to the card saved on the first payment.
     */
    public function extend(Request $request, string $slug, Billing $billing): RedirectResponse
    {
        $site = Site::query()->where('slug', $slug)->firstOrFail();
        $this->authorizeManage($request, $site);

        $owner = $site->owner;

        try {
            if ($owner !== null && $billing->extendWithSavedCard($site, $owner)) {
                return back()->with('status', 'Extended by one day.');
            }
        } catch (\Throwable $e) {
            // A Stripe failure is already swallowed inside extendWithSavedCard;
            // this guards a DB write that fails after the charge succeeded.
            Log::error('Stripe extend failed', ['site_id' => $site->id, 'message' => $e->getMessage()]);
        }

        return back()->with('error', 'Your saved card needs confirming. Use Pay $1 for a day instead.');
    }

    /**
     * Stripe Billing Portal: the owner updates their card or cancels the plan.
     */
    public function billingPortal(Request $request, string $slug, Billing $billing): RedirectResponse
    {
        $site = Site::query()->where('slug', $slug)->firstOrFail();
        $this->authorizeManage($request, $site);

        if (! config('services.stripe.enabled')) {
            return back()->with('error', 'Payments are not switched on yet.');
        }

        $owner = $site->owner;

        if ($owner === null || $owner->stripe_customer_id === null) {
            return back()->with('error', 'There is no billing account to manage yet.');
        }

        try {
            $url = $billing->billingPortalUrl($owner, route('microsite.manage', $site->slug));
        } catch (ApiErrorException $e) {
            Log::error('Stripe billing portal failed', ['site_id' => $site->id, 'message' => $e->getMessage()]);

            return back()->with('error', 'We could not reach our payment provider. Please try again in a moment.');
        }

        return redirect()->away((string) $url);
    }

    public function regenerate(Request $request, string $slug, SiteComposer $composer): RedirectResponse
    {
        $site = Site::query()->where('slug', $slug)->firstOrFail();
        $this->authorizeManage($request, $site);

        $validated = $request->validate([
            'business_name' => ['required', 'string', 'min:2', 'max:80'],
            'prompt' => ['required', 'string', 'min:10', 'max:1500'],
            'type' => ['required', Rule::in([SiteModel::TYPE_STANDARD, SiteModel::TYPE_EVENT, SiteModel::TYPE_SWEEPSTAKES])],
            'event_date' => ['nullable', 'date'],
            'event_time' => ['nullable', 'string', 'max:60'],
            'venue' => ['nullable', 'string', 'max:160'],
            'address' => ['nullable', 'string', 'max:200'],
            'offer_headline' => ['nullable', 'string', 'max:120'],
            'cta_label' => ['nullable', 'string', 'max:40'],
            'seo_title' => ['nullable', 'string', 'max:70'],
            'seo_description' => ['nullable', 'string', 'max:160'],
            'location' => ['nullable', 'string', 'max:120'],
            'contact_email' => ['nullable', 'email', 'max:120'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'accent_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'hero_image' => ['nullable', 'string', Rule::in(['auto', 'none', ...HeroArt::ILLUSTRATIONS])],
            'hero_overlay' => ['boolean'],
            'ga4_id' => ['nullable', 'string', 'regex:/^G-[A-Z0-9]{4,20}$/'],
            'remove_branding' => ['boolean'],
            'verify_emails' => ['boolean'],
            'verify_phones' => ['boolean'],
            'require_confirmation' => ['boolean'],
            'require_email_code' => ['boolean'],
            'collect_photo' => ['boolean'],
            'collect_dob' => ['boolean'],
            'webhook_url' => ['nullable', 'url', 'max:300'],
            'go_live_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:go_live_at'],
            'custom_domain' => ['nullable', 'string', 'max:120'],
            'prize' => ['nullable', 'required_if:type,'.SiteModel::TYPE_SWEEPSTAKES, 'string', 'max:300'],
            'prize_value' => ['nullable', 'string', 'max:60'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'sponsor' => ['nullable', 'string', 'max:160'],
            'eligibility' => ['nullable', 'string', 'max:400'],
            'amoe' => ['nullable', 'string', 'max:800'],
        ]);

        // An unticked checkbox sends no key, and regenerate() merges over the
        // site's existing inputs, so each entry add-on checkbox must be read
        // explicitly or an enabled add-on could never be switched back off.
        // (remove_branding is left out on purpose: it is plan-gated and rendered
        // disabled when the plan disallows it, so a blanket read would clear it.)
        $checkboxes = [];
        foreach (['hero_overlay', 'verify_emails', 'verify_phones', 'require_confirmation', 'require_email_code', 'collect_photo', 'collect_dob'] as $box) {
            $checkboxes[$box] = $request->boolean($box);
        }

        $composer->regenerate($site, [...$validated, ...$checkboxes]);

        return back()->with('status', 'Saved. Your live page updates within seconds.');
    }

    /**
     * Save one inline copy edit from the draft preview. The path must be on the
     * CopyEdits allowlist and already rendered by the generated content; the
     * text is plain, single-line and capped per field. Storing it bumps the
     * site version, so the cached page is rebuilt and open tabs refresh.
     */
    public function updateContent(Request $request, string $slug): JsonResponse
    {
        $site = Site::query()->where('slug', $slug)->firstOrFail();
        $this->authorizeManage($request, $site);
        $this->requireDraftJson($request, $site);

        $path = $this->editablePath($request, $site);
        $limit = (int) CopyEdits::limitFor($path);

        $text = CopyEdits::clean((string) $request->validate([
            'text' => ['required', 'string', 'max:'.$limit],
        ])['text']);

        if ($text === '') {
            return response()->json(['ok' => false, 'message' => 'Type some text, or reset the field to the generated copy.'], 422);
        }

        $overrides = $site->copyOverrides();
        $overrides[$path] = $text;
        $site->update(['content_overrides' => $overrides]);

        return $this->contentResponse($site->refresh(), $path);
    }

    /**
     * Drop the owner's override for one path, so the generated text shows again.
     */
    public function resetContent(Request $request, string $slug): JsonResponse
    {
        $site = Site::query()->where('slug', $slug)->firstOrFail();
        $this->authorizeManage($request, $site);
        $this->requireDraftJson($request, $site);

        $path = $this->editablePath($request, $site);

        $overrides = $site->copyOverrides();
        unset($overrides[$path]);
        $site->update(['content_overrides' => $overrides === [] ? null : $overrides]);

        return $this->contentResponse($site->refresh(), $path);
    }

    /**
     * Inline edits are a draft-only feature in v1 (the preview bar, and so the
     * editor, only renders on drafts), so a live site refuses them outright.
     * And the draft page is cached and served to everyone, so an edit cannot
     * carry a CSRF token: a JSON body is the stand-in, since a browser only
     * sends one cross-site after a CORS preflight, which this app never grants.
     */
    protected function requireDraftJson(Request $request, Site $site): void
    {
        abort_unless($site->isDraft(), 409, 'Copy can be edited on the draft preview only.');
        abort_unless($request->isJson(), 415, 'Send the edit as JSON.');
    }

    /**
     * The validated content path from the request: on the allowlist, and a
     * string the current generated content actually renders.
     */
    protected function editablePath(Request $request, Site $site): string
    {
        $path = (string) $request->validate([
            'path' => ['required', 'string', 'max:60', 'regex:/^[a-z_]+(\.[a-z_0-9]+)*$/'],
        ])['path'];

        abort_unless(CopyEdits::editable($site->content, $path), 422, 'That part of the page cannot be edited here.');

        return $path;
    }

    /**
     * What the page needs to update in place: the text now shown at the path,
     * whether it is the owner's or the generated text, and the new version so
     * the page's own refresh poll does not reload over the edit.
     */
    protected function contentResponse(Site $site, string $path): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'path' => $path,
            'text' => Arr::get($site->renderedContent(), $path),
            'edited' => array_key_exists($path, $site->copyOverrides()),
            'version' => $site->version(),
        ])->header('Cache-Control', 'no-store, private');
    }

    /**
     * Download this site's entries as CSV.
     */
    public function exportEntries(Request $request, string $slug): StreamedResponse
    {
        $site = Site::query()->where('slug', $slug)->firstOrFail();
        $this->authorizeManage($request, $site);

        $filename = $site->slug.'-entries-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($site): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Submitted at', 'Name', 'Email', 'Email status', 'Confirmed', 'Phone', 'Phone status', 'Date of birth', 'Message', 'Source', 'Campaign', 'Device', 'Country', 'IP', 'User agent', 'Free entry (AMOE)']);

            $site->entries()->oldest()->chunk(500, function ($chunk) use ($out): void {
                foreach ($chunk as $entry) {
                    fputcsv($out, array_map($this->csvSafe(...), [
                        $entry->created_at?->toDateTimeString(),
                        $entry->name,
                        $entry->email,
                        $entry->email_status,
                        $entry->confirmed_at ? 'yes' : 'no',
                        $entry->phone,
                        $entry->phone_status,
                        $entry->extra['dob'] ?? null,
                        $entry->message,
                        $entry->source,
                        $entry->campaign,
                        $entry->device,
                        $entry->country,
                        $entry->ip,
                        $entry->user_agent,
                        $entry->is_amoe ? 'yes' : 'no',
                    ]));
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Serve an entrant's uploaded photo — owner only (proven by the manage
     * token), streamed from the private disk, never cached, nosniff. Entrant
     * photos are personal, so they are never public.
     */
    public function entryPhoto(Request $request, string $slug, string $entry): StreamedResponse
    {
        $site = Site::query()->where('slug', $slug)->firstOrFail();
        $this->authorizeManage($request, $site);

        // Resolve the entry only after proving ownership, scoped to this site,
        // so probing entry ids never reveals whether one exists.
        $record = $site->entries()->whereKey($entry)->first();
        abort_unless($record !== null && filled($record->photo_path), 404);

        $disk = Storage::disk(SiteAsset::DISK);
        abort_unless($disk->exists($record->photo_path), 404);

        return $disk->response($record->photo_path, 'entry.jpg', [
            'Content-Type' => 'image/jpeg',
            'Content-Disposition' => 'inline; filename="entry.jpg"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=0, no-store',
        ]);
    }

    /**
     * The reporting breakdowns for a site: top values per dimension with views,
     * entries and conversion. Cookieless, from our own counts.
     *
     * @return array<string, list<array{key: string, views: int, entries: int, conv: float}>>
     */
    protected function breakdowns(Site $site): array
    {
        $rows = $site->breakdowns()->get();

        $top = fn (string $dimension): array => $rows
            ->where('dimension', $dimension)
            ->sortByDesc('views')
            ->take(8)
            ->map(fn ($row): array => [
                'key' => $row->key,
                'views' => $row->views,
                'entries' => $row->entries,
                'conv' => $row->views > 0 ? round($row->entries / $row->views * 100, 1) : 0.0,
            ])
            ->values()
            ->all();

        return [
            'source' => $top(SiteBreakdown::SOURCE),
            'campaign' => $top(SiteBreakdown::CAMPAIGN),
            'device' => $top(SiteBreakdown::DEVICE),
        ];
    }

    /**
     * The most-clicked tracked links and buttons for a site over the last 30
     * days (the Pro click-tracking add-on): a row per element with a human name
     * and its total, most-clicked first. Empty when the add-on is off or no
     * clicks have landed. Anonymous — from our own daily roll-up, no cookies.
     *
     * @return list<array{name: string, kind: string, clicks: int}>
     */
    protected function clickReport(Site $site): array
    {
        if (! $site->tracksClicks()) {
            return [];
        }

        $since = Carbon::today()->subDays(29)->toDateString();

        $totals = $site->clickStats()
            ->where('stat_date', '>=', $since)
            ->get()
            ->groupBy('element_id')
            ->map(fn ($rows): int => (int) $rows->sum('clicks'));

        $rows = [];

        foreach ($site->trackIds() as $slot => $id) {
            $clicks = (int) ($totals[$id] ?? 0);

            if ($clicks <= 0) {
                continue;
            }

            $meta = ClickTargets::SLOTS[$slot] ?? ['kind' => 'link', 'name' => $slot];
            $rows[] = ['name' => $meta['name'], 'kind' => $meta['kind'], 'clicks' => $clicks];
        }

        usort($rows, fn (array $a, array $b): int => $b['clicks'] <=> $a['clicks']);

        return $rows;
    }

    /**
     * Neutralise CSV formula injection: a cell that a spreadsheet would treat
     * as a formula (leading = + - @, tab or CR) is prefixed with a quote so it
     * is read as text.
     */
    protected function csvSafe(?string $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }

    /**
     * Live stats for the dashboard: the headline counters and the 30-day
     * series, polled by the manage page so the chart updates without a reload.
     */
    public function stats(Request $request, string $slug): JsonResponse
    {
        $site = Site::query()->where('slug', $slug)->firstOrFail();
        $this->authorizeManage($request, $site);

        $avg = $site->avgDwellSeconds();
        $range = $this->normalizeRange($request->query('range'));

        return response()->json([
            'views' => (int) $site->view_count,
            'entries' => (int) $site->entry_count,
            'conversion' => $site->view_count > 0 ? round($site->entry_count / $site->view_count * 100, 1) : 0.0,
            'dwell' => $avg > 0 ? sprintf('%d:%02d', intdiv($avg, 60), $avg % 60) : '—',
            'live' => LiveViewers::count($site->id),
            'range' => $range,
            'caption' => $this->rangeCaption($range),
            'series' => $this->series($site, $range),
        ]);
    }

    /**
     * The chart series for a range, oldest first, with empty buckets filled in
     * so the chart has a point for every minute / hour / day / month in the
     * window. Each range reads the matching analytics tier: hour → minute rows,
     * day → hourly rows, week/month → daily rows, year → daily rows by month.
     *
     * @return array<int, array{label: string, views: int, entries: int}>
     */
    protected function series(Site $site, string $range): array
    {
        return match ($range) {
            'hour' => $this->minuteSeries($site, 60),
            'day' => $this->hourSeries($site, 24),
            'week' => $this->daySeries($site, 7),
            'year' => $this->monthSeries($site, 12),
            'all' => $this->allSeries($site),
            default => $this->daySeries($site, 30),
        };
    }

    /**
     * Only a known range reaches the query layer; anything else is the default.
     */
    protected function normalizeRange(mixed $range): string
    {
        return in_array($range, self::RANGES, true) ? (string) $range : 'month';
    }

    /**
     * The human caption under the chart for a range.
     */
    protected function rangeCaption(string $range): string
    {
        return match ($range) {
            'hour' => 'Views per minute, last hour',
            'day' => 'Views per hour, last 24 hours',
            'week' => 'Views per day, last 7 days',
            'year' => 'Views per month, last 12 months',
            'all' => 'Views per month, all time',
            default => 'Views per day, last 30 days',
        };
    }

    /**
     * @return array<int, array{label: string, views: int, entries: int}>
     */
    protected function minuteSeries(Site $site, int $count): array
    {
        $end = Carbon::now()->startOfMinute();
        $start = $end->copy()->subMinutes($count - 1);

        $rows = SiteMinuteStat::query()
            ->where('site_id', $site->id)
            ->where('stat_minute', '>=', $start)
            ->get()
            ->keyBy(fn ($row): string => $row->stat_minute->format('Y-m-d H:i'));

        $points = [];

        for ($t = $start->copy(); $t->lte($end); $t->addMinute()) {
            $key = $t->format('Y-m-d H:i');
            $points[] = [
                'label' => $t->format('g:i a'),
                'views' => (int) ($rows[$key]->views ?? 0),
                'entries' => (int) ($rows[$key]->entries ?? 0),
            ];
        }

        return $points;
    }

    /**
     * @return array<int, array{label: string, views: int, entries: int}>
     */
    protected function hourSeries(Site $site, int $count): array
    {
        $end = Carbon::now()->startOfHour();
        $start = $end->copy()->subHours($count - 1);

        $rows = SiteHourlyStat::query()
            ->where('site_id', $site->id)
            ->where('stat_hour', '>=', $start)
            ->get()
            ->keyBy(fn ($row): string => $row->stat_hour->format('Y-m-d H'));

        $points = [];

        for ($t = $start->copy(); $t->lte($end); $t->addHour()) {
            $key = $t->format('Y-m-d H');
            $points[] = [
                'label' => $t->format('M j, g a'),
                'views' => (int) ($rows[$key]->views ?? 0),
                'entries' => (int) ($rows[$key]->entries ?? 0),
            ];
        }

        return $points;
    }

    /**
     * @return array<int, array{label: string, views: int, entries: int}>
     */
    protected function daySeries(Site $site, int $count): array
    {
        $end = Carbon::today();
        $start = $end->copy()->subDays($count - 1);

        $rows = SiteStat::query()
            ->where('site_id', $site->id)
            ->where('stat_date', '>=', $start->toDateString())
            ->get()
            ->keyBy(fn ($row): string => $row->stat_date->toDateString());

        $points = [];

        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $key = $day->toDateString();
            $points[] = [
                'label' => $day->format('M j'),
                'views' => (int) ($rows[$key]->views ?? 0),
                'entries' => (int) ($rows[$key]->entries ?? 0),
            ];
        }

        return $points;
    }

    /**
     * @return array<int, array{label: string, views: int, entries: int}>
     */
    protected function monthSeries(Site $site, int $count): array
    {
        $end = Carbon::now()->startOfMonth();
        $start = $end->copy()->subMonths($count - 1);

        $byMonth = SiteStat::query()
            ->where('site_id', $site->id)
            ->where('stat_date', '>=', $start->toDateString())
            ->get()
            ->groupBy(fn ($row): string => $row->stat_date->format('Y-m'));

        $points = [];

        for ($month = $start->copy(); $month->lte($end); $month->addMonth()) {
            $rows = $byMonth[$month->format('Y-m')] ?? collect();
            $points[] = [
                'label' => $month->format('M Y'),
                'views' => (int) $rows->sum('views'),
                'entries' => (int) $rows->sum('entries'),
            ];
        }

        return $points;
    }

    /**
     * All-time, bucketed by month from the site's first recorded day to now.
     *
     * @return array<int, array{label: string, views: int, entries: int}>
     */
    protected function allSeries(Site $site): array
    {
        $first = SiteStat::query()->where('site_id', $site->id)->min('stat_date');
        $start = $first ? Carbon::parse($first)->startOfMonth() : Carbon::now()->startOfMonth();
        $months = $start->diffInMonths(Carbon::now()->startOfMonth()) + 1;

        return $this->monthSeries($site, max(1, (int) $months));
    }
}
