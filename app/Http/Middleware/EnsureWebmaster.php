<?php

namespace App\Http\Middleware;

use App\Models\Owner;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWebmaster
{
    /**
     * Admit only webmaster accounts. A signed-in account that is not on the
     * allowlist gets a 404 rather than a 403, so the admin area does not
     * announce its own existence to a logged-in owner probing for it.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof Owner || ! $user->isWebmaster()) {
            abort(404);
        }

        return $next($request);
    }
}
