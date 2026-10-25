<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Site;
use Illuminate\Http\Request;

/**
 * Shared owner authorization: control of a site is proven by its manage token,
 * held in a cookie or passed once as ?t= on the share link.
 */
trait ManagesSite
{
    protected function authorizeManage(Request $request, Site $site): void
    {
        $token = $request->cookie($site->cookieName()) ?? $request->query('t');

        abort_unless(
            $site->managedBy(is_string($token) ? $token : null),
            403,
            'This management link is not valid for this site.',
        );
    }
}
