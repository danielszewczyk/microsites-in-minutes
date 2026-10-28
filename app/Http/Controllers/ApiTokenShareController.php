<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesSite;
use App\Models\Site;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * A one-time hand-off of a site's Import-API credentials. The owner generates a
 * single-use link from the dashboard and sends it to their developer over their
 * own channel; opening it shows a confirm page, and only clicking "Reveal"
 * consumes the link and shows the endpoint and token once. This keeps the token
 * off the owner's screen (screen shares, screenshots) and out of plaintext
 * email or chat.
 *
 * The confirm step matters: link-preview bots (Slack, iMessage, email scanners)
 * fetch a URL with GET, so revealing on GET would let them burn the link before
 * the developer ever opens it. Only the POST reveals and consumes.
 */
class ApiTokenShareController extends Controller
{
    use ManagesSite;

    /** How long an unopened share link lives before it expires. */
    protected const int TTL_MINUTES = 60;

    /** The cache key a share id is stored under. */
    protected static function cacheKey(string $id): string
    {
        return 'api-share:'.$id;
    }

    /**
     * Mint a single-use link that reveals this site's API credentials once.
     */
    public function create(Request $request, string $slug): RedirectResponse
    {
        $site = Site::query()->where('slug', $slug)->firstOrFail();
        $this->authorizeManage($request, $site);

        // authorizeManage has already proven the token is valid; read the plain
        // token so the one-time page can show the exact bearer credential. It is
        // encrypted at rest in the cache so a DB dump of the cache store can't
        // read it.
        $token = (string) ($request->cookie($site->cookieName()) ?? $request->query('t'));

        $id = Str::random(40);

        Cache::put(self::cacheKey($id), [
            'business_name' => $site->inputs['business_name'] ?? 'your site',
            'endpoint' => route('api.entries.store', $site->slug, absolute: true),
            'token' => Crypt::encryptString($token),
        ], now()->addMinutes(self::TTL_MINUTES));

        return back()->with('api_share_url', route('api-token.reveal', $id));
    }

    /**
     * GET: a confirm page. It does NOT consume the link (so a link-preview bot's
     * fetch can't spend it), it only reports whether the link is still live.
     */
    public function show(string $id): View
    {
        return view('manage.api-credentials', [
            'state' => Cache::has(self::cacheKey($id)) ? 'confirm' : 'expired',
            'id' => $id,
            'data' => null,
        ]);
    }

    /**
     * POST: reveal the credentials once. Reading consumes the link, so a second
     * reveal (or a link that has expired or was never valid) is spent.
     */
    public function reveal(string $id): View
    {
        $stored = Cache::pull(self::cacheKey($id));

        $data = null;

        if (is_array($stored)) {
            $data = $stored;
            $data['token'] = Crypt::decryptString($stored['token']);
        }

        return view('manage.api-credentials', [
            'state' => $data === null ? 'expired' : 'revealed',
            'id' => $id,
            'data' => $data,
        ]);
    }
}
