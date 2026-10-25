<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesSite;
use App\Jobs\ProvisionDomain;
use App\Models\Domain;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Buy a vanity domain for a site and serve the site on it. Buying and the
 * Cloudflare for SaaS hostname/SSL are stubbed until the registrar + Cloudflare
 * are wired at deploy (config('domains.stub')): no domain is registered and no
 * money changes hands, but the data model, the flow and the Host-based routing
 * all work. Gated to plans that include custom domains, and to the site's owner.
 */
class DomainController extends Controller
{
    use ManagesSite;

    public function search(Request $request, string $slug): RedirectResponse
    {
        $site = Site::query()->where('slug', $slug)->firstOrFail();
        $this->authorizeManage($request, $site);
        $this->ensureAllowed($site);

        $domain = Domain::normalise((string) $request->input('domain', ''));

        if ($domain === null) {
            return back()->withErrors(['domain' => 'Enter a domain like submityoursmile.com.']);
        }

        $available = ! Domain::query()->where('domain', $domain)->exists() && ! Domain::isReserved($domain);

        return back()->with('domain_search', [
            'domain' => $domain,
            'available' => $available,
            'price_cents' => (int) config('domains.price_cents'),
        ]);
    }

    public function buy(Request $request, string $slug): RedirectResponse
    {
        $site = Site::query()->where('slug', $slug)->firstOrFail();
        $this->authorizeManage($request, $site);
        $this->ensureAllowed($site);

        $domain = Domain::normalise((string) $request->input('domain', ''));

        if ($domain === null) {
            return back()->withErrors(['domain' => 'That domain does not look right.']);
        }

        if (Domain::isReserved($domain)) {
            return back()->withErrors(['domain' => 'That domain is not available.']);
        }

        if (Domain::query()->where('domain', $domain)->exists()) {
            return back()->withErrors(['domain' => 'That domain is already taken.']);
        }

        // One vanity domain per site in the MVP: replace any existing one.
        $site->domains()->delete();

        $record = $site->domains()->create([
            'owner_id' => $site->owner_id,
            'domain' => $domain,
            'status' => Domain::REGISTERING,
            'price_cents' => (int) config('domains.price_cents'),
            'cost_cents' => (int) config('domains.cost_cents'),
            'expires_at' => now()->addYear(),
        ]);

        // We register and point it — no DNS for the customer. The stub finishes
        // at once; a real registrar + SSL runs on the queue and the page flips
        // to live on its own.
        $this->provision($record);

        $note = config('domains.stub') ? ' (demo — nothing registered or charged)' : '';

        return back()->with('status', "Setting up {$domain} — registering it and issuing SSL, no DNS needed{$note}.");
    }

    /**
     * Current setup status, for the dashboard to poll while a domain registers.
     */
    public function status(Request $request, string $slug): JsonResponse
    {
        $site = Site::query()->where('slug', $slug)->firstOrFail();
        $this->authorizeManage($request, $site);

        $domain = $site->domain();

        return response()->json([
            'status' => $domain?->status,
            'active' => $domain?->isActive() ?? false,
        ])->header('Cache-Control', 'no-store');
    }

    /**
     * Retry provisioning a domain that failed.
     */
    public function retry(Request $request, string $slug): RedirectResponse
    {
        $site = Site::query()->where('slug', $slug)->firstOrFail();
        $this->authorizeManage($request, $site);
        $this->ensureAllowed($site);

        $domain = $site->domains()->firstOrFail();

        abort_unless($domain->status === Domain::FAILED, 422, 'This domain is not in a failed state.');

        $domain->update(['status' => Domain::REGISTERING]);
        $this->provision($domain);

        return back()->with('status', 'Retrying setup for '.$domain->domain.'.');
    }

    /**
     * Kick off provisioning: inline for the stub so the demo completes now,
     * queued for a real registrar so the request does not block.
     */
    protected function provision(Domain $domain): void
    {
        config('domains.stub')
            ? ProvisionDomain::dispatchSync($domain->id)
            : ProvisionDomain::dispatch($domain->id);
    }

    public function remove(Request $request, string $slug): RedirectResponse
    {
        $site = Site::query()->where('slug', $slug)->firstOrFail();
        $this->authorizeManage($request, $site);

        $site->domains()->delete();

        return back()->with('status', 'Custom domain removed.');
    }

    protected function ensureAllowed(Site $site): void
    {
        abort_unless(
            $site->owner->allows('custom_domain'),
            403,
            'Custom domains are available on paid plans. Upgrade to add one.',
        );
    }
}
