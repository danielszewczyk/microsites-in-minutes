<?php

namespace App\Http\Controllers;

use App\Models\Site;
use Illuminate\Contracts\View\View;

/**
 * The webmaster dashboard: an owner on the allowlist (config/webmaster.php) sees
 * every site on the service at /admin. Read-only for now — a directory with
 * links, not a control panel.
 */
class WebmasterController extends Controller
{
    public function index(): View
    {
        $sites = Site::query()
            ->with('owner')
            ->latest()
            ->paginate(50);

        return view('webmaster.index', [
            'sites' => $sites,
            'totalSites' => Site::query()->count(),
        ]);
    }
}
