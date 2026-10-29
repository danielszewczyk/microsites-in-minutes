<?php

use App\Http\Controllers\AlarmController;
use App\Http\Controllers\ApiTokenShareController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DomainController;
use App\Http\Controllers\MicrositeController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\ShortLinkController;
use App\Http\Controllers\SiteAssetController;
use App\Http\Controllers\SiteBuilderController;
use App\Http\Controllers\SiteManageController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\VerificationController;
use App\Http\Controllers\WebmasterController;
use Illuminate\Support\Facades\Route;

/*
 * The builder: the landing page with the prompt box, and publishing a site.
 */
Route::get('/', [SiteBuilderController::class, 'index'])->name('home');
Route::post('/sites', [SiteBuilderController::class, 'store'])
    ->middleware(['auth', 'verified', 'throttle:10,1'])
    ->name('sites.store');

/*
 * Account auth: register, verify email, log in. Publishing requires a verified
 * account; the MCP OAuth consent flow also logs in here.
 */
Route::middleware('guest')->group(function (): void {
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
});
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');
Route::middleware('auth')->group(function (): void {
    Route::get('/email/verify', [VerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [VerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verification-notification', [VerificationController::class, 'resend'])
        ->middleware('throttle:6,1')->name('verification.send');
});

/*
 * The webmaster dashboard: a directory of every site on the service, open only
 * to accounts on the config/webmaster.php allowlist.
 */
Route::get('/admin', [WebmasterController::class, 'index'])
    ->middleware(['auth', 'webmaster'])
    ->name('admin.sites');

/*
 * Site-wide SEO endpoints.
 */
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');
Route::get('/og.png', [SeoController::class, 'homeOg'])
    ->middleware('throttle:120,1,home-og')->name('home.og');
Route::get('/favicon.svg', [SeoController::class, 'homeFavicon'])
    ->middleware('throttle:120,1,home-favicon')->name('home.favicon');
Route::get('/icon.png', [SeoController::class, 'homeIcon'])
    ->middleware('throttle:120,1,home-icon')->name('home.icon');

/*
 * Short links: a public redirect that counts the click and forwards to the site
 * with the link's UTM tags.
 */
Route::get('/l/{code}', [ShortLinkController::class, 'redirect'])
    ->where('code', '[A-Za-z0-9]{1,16}')
    ->middleware('throttle:120,1,link-redirect')
    ->name('link.redirect');

/*
 * A one-time link that reveals a site's Import-API credentials once, so the
 * owner can hand them to a developer without putting the token in plaintext.
 */
Route::get('/dev-credentials/{id}', [ApiTokenShareController::class, 'show'])
    ->where('id', '[A-Za-z0-9]{1,64}')
    ->middleware('throttle:30,1,api-token-reveal')
    ->name('api-token.reveal');
Route::post('/dev-credentials/{id}', [ApiTokenShareController::class, 'reveal'])
    ->where('id', '[A-Za-z0-9]{1,64}')
    ->middleware('throttle:30,1,api-token-reveal-confirm')
    ->name('api-token.reveal.confirm');

/*
 * Stripe calls this with signed events. It is outside the CSRF check and is
 * trusted only when the signature verifies against the webhook secret.
 */
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle'])->name('stripe.webhook');

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

    Route::get('/embed', [MicrositeController::class, 'embed'])
        ->middleware('throttle:90,1,microsite-embed')
        ->name('microsite.embed');

    Route::get('/confirm/{token}', [MicrositeController::class, 'confirm'])
        ->where('token', '[A-Za-z0-9]{1,64}')
        ->middleware('throttle:30,1,microsite-confirm')
        ->name('microsite.confirm');

    Route::post('/enter', [MicrositeController::class, 'enter'])
        ->middleware('throttle:5,1,microsite-enter')
        ->name('microsite.enter');

    Route::post('/send-code', [MicrositeController::class, 'sendCode'])
        ->middleware('throttle:10,1,microsite-send-code')
        ->name('microsite.send-code');

    Route::post('/ping', [MicrositeController::class, 'ping'])
        ->middleware('throttle:30,1,microsite-ping')
        ->name('microsite.ping');

    Route::post('/track', [MicrositeController::class, 'track'])
        ->middleware('throttle:60,1,microsite-track')
        ->name('microsite.track');

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
    Route::post('/manage/publish', [SiteManageController::class, 'publish'])
        ->middleware('throttle:20,1,microsite-publish')->name('microsite.publish');
    Route::post('/manage/regenerate', [SiteManageController::class, 'regenerate'])
        ->middleware('throttle:30,1,microsite-regenerate')
        ->name('microsite.regenerate');
    Route::post('/manage/billing/{plan}', [SiteManageController::class, 'checkout'])
        ->whereIn('plan', ['day', 'monthly'])
        ->middleware('throttle:20,1,microsite-billing')
        ->name('microsite.billing.checkout');
    Route::post('/manage/billing/extend', [SiteManageController::class, 'extend'])
        ->middleware('throttle:20,1,microsite-billing')
        ->name('microsite.billing.extend');
    Route::get('/manage/entries.csv', [SiteManageController::class, 'exportEntries'])->name('microsite.entries.export');
    Route::get('/manage/stats.json', [SiteManageController::class, 'stats'])
        ->middleware('throttle:120,1,manage-stats')->name('microsite.stats');
    Route::post('/manage/api-token/share', [ApiTokenShareController::class, 'create'])
        ->middleware('throttle:10,1,api-token-share')->name('microsite.api-token.share');
    Route::get('/entries/{entry}/photo', [SiteManageController::class, 'entryPhoto'])
        ->whereNumber('entry')
        ->middleware('throttle:300,1,entry-photo')
        ->name('microsite.entry.photo');

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

    /*
     * Threshold alarms (views / entries / storage).
     */
    Route::post('/alarms', [AlarmController::class, 'store'])
        ->middleware('throttle:30,1,alarm-store')->name('microsite.alarms.store');
    Route::post('/alarms/{alarm}/delete', [AlarmController::class, 'destroy'])
        ->middleware('throttle:30,1,alarm-delete')->name('microsite.alarms.destroy');
});
