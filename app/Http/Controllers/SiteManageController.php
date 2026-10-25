<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesSite;
use App\Models\Site;
use App\Models\Site as SiteModel;
use App\Sites\HeroArt;
use App\Sites\SiteComposer;
use App\Sites\UsageReport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
            'plan' => $site->owner->plan(),
            'usage' => UsageReport::for($site->owner),
            'assets' => $site->assets()->get()->keyBy('slot'),
            'uploadQr' => SiteAssetController::qrFor($site, $token),
            'uploadUrl' => route('microsite.upload', $site->slug).'?t='.$token,
        ]);
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
            'location' => ['nullable', 'string', 'max:120'],
            'contact_email' => ['nullable', 'email', 'max:120'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'accent_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'hero_image' => ['nullable', 'string', Rule::in(['auto', 'none', ...HeroArt::ILLUSTRATIONS])],
            'ga4_id' => ['nullable', 'string', 'regex:/^G-[A-Z0-9]{4,20}$/'],
            'remove_branding' => ['boolean'],
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
            fputcsv($out, ['Submitted at', 'Name', 'Email', 'Phone', 'Message', 'Free entry (AMOE)']);

            $site->entries()->oldest()->chunk(500, function ($chunk) use ($out): void {
                foreach ($chunk as $entry) {
                    fputcsv($out, array_map($this->csvSafe(...), [
                        $entry->created_at?->toDateTimeString(),
                        $entry->name,
                        $entry->email,
                        $entry->phone,
                        $entry->message,
                        $entry->is_amoe ? 'yes' : 'no',
                    ]));
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
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
