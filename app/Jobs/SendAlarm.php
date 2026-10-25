<?php

namespace App\Jobs;

use App\Models\SiteAlarm;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Emails the owner that a site crossed an alarm threshold.
 */
class SendAlarm implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $alarmId, public int $value) {}

    public function handle(): void
    {
        $alarm = SiteAlarm::query()->with('site')->find($this->alarmId);

        if ($alarm === null || $alarm->site === null) {
            return;
        }

        $name = $alarm->site->inputs['business_name'] ?? 'your site';

        try {
            Mail::raw(
                "Heads up — your site \"{$name}\" just reached ".number_format($this->value)." {$alarm->metric}.\n\n{$alarm->site->url()}",
                fn ($message) => $message->to($alarm->email)->subject("Alarm: {$name} reached ".$alarm->label()),
            );
        } catch (\Throwable $e) {
            Log::warning('Alarm email failed', ['alarm' => $alarm->id, 'error' => $e->getMessage()]);
        }
    }
}
