<?php

namespace App\Domains;

use App\Models\Domain;
use Illuminate\Support\Facades\Log;

/**
 * The MVP provisioner: simulates registering and pointing a domain without any
 * registrar call, DNS change or charge. It marks the domain active so the whole
 * zero-touch flow can be exercised locally. Swap this binding for a real
 * registrar + Cloudflare provisioner at deploy.
 */
class StubProvisioner implements DomainProvisioner
{
    public function provision(Domain $domain): string
    {
        Log::info('[stub] would register and point domain', ['domain' => $domain->domain]);

        $domain->forceFill([
            'verified_at' => now(),
            'expires_at' => $domain->expires_at ?? now()->addYear(),
        ])->save();

        return Domain::ACTIVE;
    }
}
