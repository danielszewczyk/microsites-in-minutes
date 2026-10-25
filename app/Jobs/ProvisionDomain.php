<?php

namespace App\Jobs;

use App\Domains\DomainProvisioner;
use App\Models\Domain;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Registers a bought domain and points it at us, then marks it active — the
 * zero-touch setup. Queued because a real registrar + SSL issuance takes time;
 * the customer sees "setting up…" and the page flips to live on its own.
 */
class ProvisionDomain implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $domainId) {}

    public function handle(DomainProvisioner $provisioner): void
    {
        $domain = Domain::query()->find($this->domainId);

        if ($domain === null) {
            return;
        }

        try {
            $status = $provisioner->provision($domain);
        } catch (\Throwable $e) {
            Log::error('Domain provisioning failed', ['domain' => $domain->domain, 'error' => $e->getMessage()]);
            $status = Domain::FAILED;
        }

        $domain->update(['status' => $status]);
    }
}
