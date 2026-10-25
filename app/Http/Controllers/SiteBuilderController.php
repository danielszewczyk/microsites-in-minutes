<?php

namespace App\Http\Controllers;

use App\Http\Requests\SiteBuilderRequest;
use App\Sites\HeroArt;
use App\Sites\Plans;
use App\Sites\SiteComposer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * The front door: the landing page with the prompt box and the builder form,
 * and the endpoint that generates and publishes a microsite.
 */
class SiteBuilderController extends Controller
{
    public function index(): View
    {
        return view('builder.index', [
            'illustrations' => HeroArt::ILLUSTRATIONS,
            'plans' => Plans::all(),
        ]);
    }

    public function store(SiteBuilderRequest $request, SiteComposer $composer): RedirectResponse
    {
        try {
            ['site' => $site, 'token' => $token] = $composer->publish($request->validated());
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors(['prompt' => $e->getMessage()]);
        }

        return redirect()
            ->route('microsite.manage', $site->slug)
            ->withCookie(cookie($site->cookieName(), $token, 60 * 24 * 30))
            ->with('created', [
                'url' => $site->url(),
                'manage_url' => route('microsite.manage', $site->slug).'?t='.$token,
            ]);
    }
}
