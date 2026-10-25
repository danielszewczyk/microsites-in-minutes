<?php

namespace App\Jobs;

use App\Leads\EmailVerifier;
use App\Models\SiteEntry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Verifies one entry's email deliverability (Bouncer) off the request path, so
 * capturing a lead never waits on an external API.
 */
class VerifyEntryEmail implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $entryId) {}

    public function handle(EmailVerifier $verifier): void
    {
        $entry = SiteEntry::query()->find($this->entryId);

        if ($entry === null || blank($entry->email)) {
            return;
        }

        ['status' => $status, 'score' => $score] = $verifier->verify($entry->email);

        $entry->update(['email_status' => $status, 'email_score' => $score]);
    }
}
