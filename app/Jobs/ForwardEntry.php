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
            Http::timeout(10)->withoutRedirecting()->asJson()->post($url, [
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
     * Allow only public http(s) URLs. Resolves every A and AAAA record and
     * refuses the request if any resolves to a private, reserved, loopback,
     * link-local, CGNAT or IPv4-mapped-IPv6 address — closing SSRF via IPv6
     * literals, mapped forms and multi-record hosts. Redirects are not followed
     * (see handle). A residual DNS-rebinding window remains between this check
     * and the HTTP connect; acceptable for a queued, paid-only webhook.
     */
    protected function isPublicUrl(string $url): bool
    {
        $parts = parse_url($url);

        if (! in_array($parts['scheme'] ?? '', ['http', 'https'], true) || empty($parts['host'])) {
            return false;
        }

        $host = trim(strtolower($parts['host']), '[]');

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $this->isPublicIp($host);
        }

        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.internal') || str_ends_with($host, '.local')) {
            return false;
        }

        $ips = [];
        foreach ([DNS_A, DNS_AAAA] as $type) {
            foreach (@dns_get_record($host, $type) ?: [] as $record) {
                $ips[] = $record['ip'] ?? $record['ipv6'] ?? null;
            }
        }
        $ips = array_filter($ips);

        if ($ips === []) {
            return false; // unresolved — refuse rather than guess
        }

        foreach ($ips as $ip) {
            if (! $this->isPublicIp($ip)) {
                return false;
            }
        }

        return true;
    }

    /**
     * An IP that is safe to connect to: not private, reserved, loopback,
     * link-local, CGNAT, nor an IPv4-mapped-IPv6 form of those.
     */
    protected function isPublicIp(string $ip): bool
    {
        $bin = @inet_pton($ip);

        if ($bin === false) {
            return false;
        }

        // Normalise an IPv4-mapped IPv6 address (::ffff:a.b.c.d) to its IPv4.
        if (strlen($bin) === 16 && str_starts_with($bin, str_repeat("\x00", 10)."\xff\xff")) {
            $ip = (string) inet_ntop(substr($bin, 12));
        }

        // CGNAT 100.64.0.0/10 is not covered by PHP's range flags.
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && (ip2long($ip) & 0xFFC00000) === (ip2long('100.64.0.0') & 0xFFC00000)) {
            return false;
        }

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }
}
