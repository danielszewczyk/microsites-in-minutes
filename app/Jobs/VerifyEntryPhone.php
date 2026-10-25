<?php

namespace App\Jobs;

use App\Leads\PhoneVerifier;
use App\Models\SiteEntry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Verifies one entry's phone number (a lookup) off the request path.
 */
class VerifyEntryPhone implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $entryId) {}

    public function handle(PhoneVerifier $verifier): void
    {
        $entry = SiteEntry::query()->find($this->entryId);

        if ($entry === null || blank($entry->phone)) {
            return;
        }

        ['status' => $status, 'line' => $line] = $verifier->verify($entry->phone);

        $entry->update(['phone_status' => $status, 'phone_line' => $line]);
    }
}
