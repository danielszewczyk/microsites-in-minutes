<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesSite;
use App\Models\Site;
use App\Models\Site as SiteModel;
use App\Models\SiteAsset;
use App\Models\SiteBreakdown;
use App\Sites\HeroArt;
use App\Sites\Plans;
use App\Sites\SiteComposer;
use App\Sites\UsageReport;
use App\Support\LiveViewers;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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
            'stats' => $this->recentStats($site),
            'live' => LiveViewers::count($site->id),
            'plan' => $site->owner->plan(),
            'launchFree' => Plans::launchFree(),
            'usage' => UsageReport::for($site->owner),
            'breakdowns' => $this->breakdowns($site),
            'domain' => $site->domain(),
            'og' => [
                'image' => route('microsite.og', $site->slug).'?v='.substr($site->version(), -8),
                'title' => $site->content['meta']['title'] ?? ($site->content['hero']['headline'] ?? ''),
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

        if ($site->isDraft()) {
            $site->update(['status' => Site::STATUS_PUBLISHED, 'published_at' => $site->published_at ?? now()]);
        }

        return back()->with('status', 'Your site is now live and accepting entries.');
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
            'ga4_id' => ['nullable', 'string', 'regex:/^G-[A-Z0-9]{4,20}$/'],
            'remove_branding' => ['boolean'],
            'verify_emails' => ['boolean'],
            'verify_phones' => ['boolean'],
            'require_confirmation' => ['boolean'],
            'collect_photo' => ['boolean'],
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

        $composer->regenerate($site, $validated);

        return back()->with('status', 'Saved. Your live page updates within seconds.');
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
            fputcsv($out, ['Submitted at', 'Name', 'Email', 'Email status', 'Confirmed', 'Phone', 'Phone status', 'Message', 'Source', 'Campaign', 'Device', 'Country', 'IP', 'User agent', 'Free entry (AMOE)']);

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

        return response()->json([
            'views' => (int) $site->view_count,
            'entries' => (int) $site->entry_count,
            'conversion' => $site->view_count > 0 ? round($site->entry_count / $site->view_count * 100, 1) : 0.0,
            'dwell' => $avg > 0 ? sprintf('%d:%02d', intdiv($avg, 60), $avg % 60) : '—',
            'live' => LiveViewers::count($site->id),
            'days' => $this->recentStats($site),
        ]);
    }

    /**
     * Views and entries per day for the last 30 days, oldest first.
     *
     * @return array<int, array{date: string, views: int, entries: int}>
     */
    protected function recentStats(Site $site): array
    {
        $since = Carbon::today()->subDays(29);

        $rows = $site->stats()
            ->where('stat_date', '>=', $since->toDateString())
            ->get()
            ->keyBy(fn ($stat): string => $stat->stat_date->toDateString());

        $days = [];

        for ($day = $since->copy(); $day->lte(Carbon::today()); $day->addDay()) {
            $key = $day->toDateString();
            $days[] = [
                'date' => $key,
                'views' => (int) ($rows[$key]->views ?? 0),
                'entries' => (int) ($rows[$key]->entries ?? 0),
            ];
        }

        return $days;
    }
}
