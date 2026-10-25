<?php

namespace App\Jobs;

use App\Models\SiteEntry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Forwards a new entry to the site's integration webhook (Zapier / Make /
 * custom, which fan out to Google Sheets, Salesforce, etc.). Runs on the queue
 * so capturing a lead never waits on the owner's endpoint. Guards against SSRF:
 * only public http(s) URLs are called.
 */
class ForwardEntry implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $entryId) {}

    public function handle(): void
    {
        $entry = SiteEntry::query()->with('site')->find($this->entryId);
        $url = $entry?->site?->settings['webhook_url'] ?? null;

        if ($entry === null || ! is_string($url) || ! $this->isPublicUrl($url)) {
            return;
        }

        try {
            Http::timeout(10)->asJson()->post($url, [
                'site' => $entry->site->slug,
                'name' => $entry->name,
                'email' => $entry->email,
                'phone' => $entry->phone,
                'message' => $entry->message,
                'source' => $entry->source,
                'campaign' => $entry->campaign,
                'device' => $entry->device,
                'country' => $entry->country,
                'created_at' => $entry->created_at?->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Entry webhook failed', ['entry' => $entry->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Allow only public http(s) URLs — block loopback, private and reserved
     * hosts so an owner cannot point the webhook at internal services.
     */
    protected function isPublicUrl(string $url): bool
    {
        $parts = parse_url($url);

        if (! in_array($parts['scheme'] ?? '', ['http', 'https'], true) || empty($parts['host'])) {
            return false;
        }

        $host = strtolower($parts['host']);
        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);

        // When it resolves to an IP, block private / reserved ranges (SSRF).
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }

        // Unresolved hostname: still block obvious internal names; otherwise
        // allow (a bogus host just fails the HTTP call harmlessly).
        return ! ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.internal'));
    }
}
