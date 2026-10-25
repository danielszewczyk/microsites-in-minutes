<?php

use App\Http\Controllers\DomainController;
use App\Http\Controllers\MicrositeController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\ShortLinkController;
use App\Http\Controllers\SiteAssetController;
use App\Http\Controllers\SiteBuilderController;
use App\Http\Controllers\SiteManageController;
use Illuminate\Support\Facades\Route;

/*
 * The builder: the landing page with the prompt box, and publishing a site.
 */
Route::get('/', [SiteBuilderController::class, 'index'])->name('home');
Route::post('/sites', [SiteBuilderController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('sites.store');

/*
 * Site-wide SEO endpoints.
 */
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');

/*
 * Short links: a public redirect that counts the click and forwards to the site
 * with the link's UTM tags.
 */
Route::get('/l/{code}', [ShortLinkController::class, 'redirect'])
    ->where('code', '[A-Za-z0-9]{1,16}')
    ->middleware('throttle:120,1,link-redirect')
    ->name('link.redirect');

/*
 * A published microsite and everything that hangs off it. The slug is the only
 * dynamic segment and is pinned to a safe shape. Each route names its own
 * throttle counter so a flood of one kind of request cannot starve another.
 */
Route::prefix('s/{slug}')->where(['slug' => '[a-z0-9-]{1,72}'])->group(function () {
    Route::get('/', [MicrositeController::class, 'show'])
        ->middleware('throttle:90,1,microsite-show')
        ->name('microsite.show');

    Route::get('/version', [MicrositeController::class, 'version'])
        ->middleware('throttle:120,1,microsite-version')
        ->name('microsite.version');

    Route::get('/rules', [MicrositeController::class, 'rules'])
        ->middleware('throttle:60,1,microsite-rules')
        ->name('microsite.rules');

    Route::post('/enter', [MicrositeController::class, 'enter'])
        ->middleware('throttle:5,1,microsite-enter')
        ->name('microsite.enter');

    Route::post('/ping', [MicrositeController::class, 'ping'])
        ->middleware('throttle:30,1,microsite-ping')
        ->name('microsite.ping');

    Route::get('/favicon.svg', [SeoController::class, 'favicon'])
        ->middleware('throttle:120,1,microsite-favicon')
        ->name('microsite.favicon');

    Route::get('/og.png', [SeoController::class, 'ogImage'])
        ->middleware('throttle:120,1,microsite-og')
        ->name('microsite.og');

    /*
     * The owner's dashboard: control proven by the manage token, not a login.
     */
    Route::get('/manage', [SiteManageController::class, 'manage'])->name('microsite.manage');
    Route::post('/manage/regenerate', [SiteManageController::class, 'regenerate'])
        ->middleware('throttle:30,1,microsite-regenerate')
        ->name('microsite.regenerate');
    Route::get('/manage/entries.csv', [SiteManageController::class, 'exportEntries'])->name('microsite.entries.export');

    /*
     * Phone handoff: upload banners, a copy deck and a rules doc from the phone.
     */
    Route::get('/upload', [SiteAssetController::class, 'form'])->name('microsite.upload');
    Route::post('/upload', [SiteAssetController::class, 'upload'])
        ->middleware('throttle:30,1,microsite-upload')
        ->name('microsite.upload.store');
    Route::post('/upload/{slot}/delete', [SiteAssetController::class, 'remove'])
        ->middleware('throttle:30,1,microsite-upload-delete')
        ->name('microsite.upload.remove');
    Route::get('/asset/{slot}', [SiteAssetController::class, 'serve'])
        ->middleware('throttle:120,1,microsite-asset')
        ->name('microsite.asset');

    /*
     * Vanity domains: buy one and serve the site on it.
     */
    Route::post('/domain/search', [DomainController::class, 'search'])
        ->middleware('throttle:30,1,domain-search')->name('microsite.domain.search');
    Route::post('/domain/buy', [DomainController::class, 'buy'])
        ->middleware('throttle:10,1,domain-buy')->name('microsite.domain.buy');
    Route::get('/domain/status', [DomainController::class, 'status'])
        ->middleware('throttle:60,1,domain-status')->name('microsite.domain.status');
    Route::post('/domain/retry', [DomainController::class, 'retry'])
        ->middleware('throttle:20,1,domain-retry')->name('microsite.domain.retry');
    Route::post('/domain/remove', [DomainController::class, 'remove'])
        ->middleware('throttle:20,1,domain-remove')->name('microsite.domain.remove');

    /*
     * Share links: build a UTM-tagged short link, or remove one.
     */
    Route::post('/links', [ShortLinkController::class, 'store'])
        ->middleware('throttle:30,1,link-store')->name('microsite.links.store');
    Route::post('/links/{link}/delete', [ShortLinkController::class, 'destroy'])
        ->middleware('throttle:30,1,link-delete')->name('microsite.links.destroy');
});
