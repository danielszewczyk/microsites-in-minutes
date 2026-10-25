<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesSite;
use App\Models\Site;
use App\Models\SiteAlarm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Owner-managed threshold alarms for a site (views / entries / storage).
 */
class AlarmController extends Controller
{
    use ManagesSite;

    public function store(Request $request, string $slug): RedirectResponse
    {
        $site = Site::query()->where('slug', $slug)->firstOrFail();
        $this->authorizeManage($request, $site);

        $validated = $request->validate([
            'metric' => ['required', Rule::in(SiteAlarm::METRICS)],
            'threshold' => ['required', 'integer', 'min:1', 'max:100000000'],
        ]);

        $threshold = $validated['metric'] === SiteAlarm::STORAGE
            ? $validated['threshold'] * 1_048_576  // owner enters MB
            : $validated['threshold'];

        $site->alarms()->create([
            'metric' => $validated['metric'],
            'threshold' => $threshold,
            'email' => $site->owner->email,
            'enabled' => true,
        ]);

        return back()->with('status', 'Alarm added.');
    }

    public function destroy(Request $request, string $slug, SiteAlarm $alarm): RedirectResponse
    {
        $site = Site::query()->where('slug', $slug)->firstOrFail();
        $this->authorizeManage($request, $site);

        abort_unless($alarm->site_id === $site->id, 404);

        $alarm->delete();

        return back()->with('status', 'Alarm removed.');
    }
}
