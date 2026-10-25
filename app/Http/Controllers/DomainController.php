<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesSite;
use App\Models\Domain;
use App\Models\Site;
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

        $taken = Domain::query()->where('domain', $domain)->exists();

        return back()->with('domain_search', [
            'domain' => $domain,
            'available' => ! $taken,
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

        if (Domain::query()->where('domain', $domain)->exists()) {
            return back()->withErrors(['domain' => 'That domain is already taken.']);
        }

        // One vanity domain per site in the MVP: replace any existing one.
        $site->domains()->delete();

        $site->domains()->create([
            'owner_id' => $site->owner_id,
            'domain' => $domain,
            'status' => Domain::PENDING_DNS,
            'price_cents' => (int) config('domains.price_cents'),
            'cost_cents' => (int) config('domains.cost_cents'),
            'expires_at' => now()->addYear(),
        ]);

        $note = config('domains.stub')
            ? 'Reserved (demo — no domain was registered and nothing was charged). '
            : '';

        return back()->with('status', $note.'Point '.$domain.' at us with a CNAME to '
            .config('domains.cname_target').', then activate it below.');
    }

    /**
     * Mark the domain live. In production this is Cloudflare for SaaS confirming
     * the hostname and issuing the certificate; here it is a stub step.
     */
    public function activate(Request $request, string $slug): RedirectResponse
    {
        $site = Site::query()->where('slug', $slug)->firstOrFail();
        $this->authorizeManage($request, $site);

        $domain = $site->domains()->firstOrFail();
        $domain->update(['status' => Domain::ACTIVE, 'verified_at' => now()]);

        return back()->with('status', $domain->domain.' is live. Your site now serves on https://'.$domain->domain);
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
            $site->owner->can('custom_domain'),
            403,
            'Custom domains are available on paid plans. Upgrade to add one.',
        );
    }
}
