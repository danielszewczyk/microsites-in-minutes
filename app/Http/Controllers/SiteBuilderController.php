<?php

namespace App\Http\Controllers;

use App\Http\Requests\SiteBuilderRequest;
use App\Models\Domain;
use App\Models\Site;
use App\Sites\HeroArt;
use App\Sites\Plans;
use App\Sites\SiteComposer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The front door: the landing page with the prompt box and the builder form,
 * and the endpoint that generates and publishes a microsite. On a vanity
 * domain, the same entrypoint serves that domain's microsite instead.
 */
class SiteBuilderController extends Controller
{
    public function index(Request $request, MicrositeController $microsite): View|Response
    {
        // A request arriving on a bought, active vanity domain serves that
        // domain's site; our own host shows the builder.
        $domain = Domain::query()->active()
            ->where('domain', Domain::normalise($request->getHost()))
            ->with('site')
            ->first();

        if ($domain?->site !== null) {
            return $microsite->showForSite($request, $domain->site);
        }

        return view('builder.index', [
            'types' => Site::TYPES,
            'illustrations' => HeroArt::ILLUSTRATIONS,
            'plans' => Plans::all(),
        ]);
    }

    public function store(SiteBuilderRequest $request, SiteComposer $composer): RedirectResponse
    {
        try {
            ['site' => $site, 'token' => $token, 'build' => $build] = $composer->publish($request->validated(), $request->user());
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors(['prompt' => $e->getMessage()]);
        }

        return redirect()
            ->route('microsite.manage', $site->slug)
            ->withCookie(cookie($site->cookieName(), $token, 60 * 24 * 30))
            ->with('created', [
                'url' => $site->url(),
                'manage_url' => route('microsite.manage', $site->slug).'?t='.$token,
                'build_ms' => $build['ms'],
                'build_cents' => $build['cents'],
            ]);
    }
}
