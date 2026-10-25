<?php

namespace App\Domains;

use App\Models\Domain;

/**
 * Registers a vanity domain and points it at us — no DNS work for the customer.
 * Because we register the name, we also control its zone, so we set the records
 * and issue the certificate automatically.
 *
 * The MVP binds a StubProvisioner (no registrar call, no charge). A real
 * implementation at deploy registers through a reseller registrar API, moves the
 * zone to our Cloudflare account, adds the proxied record to our origin, and
 * waits for SSL — then reports ACTIVE. The binding lives in AppServiceProvider.
 */
interface DomainProvisioner
{
    /**
     * Register the domain and point it at us. Returns the resulting Domain
     * status (Domain::ACTIVE when serving, Domain::REGISTERING while in flight,
     * Domain::FAILED on error) and may set verified_at / expires_at on the model.
     */
    public function provision(Domain $domain): string;
}
