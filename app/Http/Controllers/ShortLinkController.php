<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesSite;
use App\Models\ShortLink;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Short, shareable links that carry UTM tags. The public redirect counts the
 * click and forwards to the site with its tags (so the visit is attributed in
 * reporting). Building and removing links is gated to the site's owner.
 */
class ShortLinkController extends Controller
{
    use ManagesSite;

    public function redirect(string $code): RedirectResponse
    {
        $link = ShortLink::query()->where('code', $code)->firstOrFail();

        $link->increment('clicks');

        return redirect()->away($link->targetUrl(), 302);
    }

    public function store(Request $request, string $slug): RedirectResponse
    {
        $site = Site::query()->where('slug', $slug)->firstOrFail();
        $this->authorizeManage($request, $site);

        $validated = $request->validate([
            'label' => ['nullable', 'string', 'max:60'],
            'source' => ['nullable', 'string', 'max:40'],
            'medium' => ['nullable', 'string', 'max:40'],
            'campaign' => ['nullable', 'string', 'max:60'],
        ]);

        $site->shortLinks()->create([
            'owner_id' => $site->owner_id,
            'code' => ShortLink::freeCode(),
            'label' => $this->tidy($validated['label'] ?? null, false),
            'source' => $this->tidy($validated['source'] ?? null),
            'medium' => $this->tidy($validated['medium'] ?? null),
            'campaign' => $this->tidy($validated['campaign'] ?? null),
        ]);

        return back()->with('status', 'Short link created.');
    }

    public function destroy(Request $request, string $slug, ShortLink $link): RedirectResponse
    {
        $site = Site::query()->where('slug', $slug)->firstOrFail();
        $this->authorizeManage($request, $site);

        abort_unless($link->site_id === $site->id, 404);

        $link->delete();

        return back()->with('status', 'Short link removed.');
    }

    /**
     * Tidy a UTM value: trim, collapse spaces, and for tag values lowercase and
     * drop characters that have no business in a UTM parameter.
     */
    protected function tidy(?string $value, bool $isTag = true): ?string
    {
        $value = trim(preg_replace('/\s+/', ' ', (string) $value) ?? '');

        if ($value === '') {
            return null;
        }

        if ($isTag) {
            $value = strtolower((string) preg_replace('/[^\w .\-]/', '', $value));
        }

        return Str::limit($value, 60, '');
    }
}
