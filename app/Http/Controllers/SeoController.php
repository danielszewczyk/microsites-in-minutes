<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Models\SiteAsset;
use App\Support\OgImage;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Site-wide SEO endpoints: sitemap.xml (every published page), robots.txt, and
 * a small per-site favicon tinted with the site's accent colour.
 */
class SeoController extends Controller
{
    public function sitemap(): Response
    {
        $urls = [['loc' => route('home'), 'lastmod' => now()->toAtomString()]];

        Site::query()->where('status', 'published')->orderBy('id')->chunk(500, function ($chunk) use (&$urls): void {
            foreach ($chunk as $site) {
                $urls[] = ['loc' => $site->url(), 'lastmod' => $site->updated_at?->toAtomString()];

                if ($site->isSweepstakes() && is_array($site->content['rules'] ?? null)) {
                    $urls[] = ['loc' => route('microsite.rules', $site->slug), 'lastmod' => $site->updated_at?->toAtomString()];
                }
            }
        });

        $xml = view('seo.sitemap', ['urls' => $urls])->render();

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    public function robots(): Response
    {
        $body = implode("\n", [
            'User-agent: *',
            'Allow: /',
            'Disallow: /s/*/manage',
            'Sitemap: '.route('sitemap'),
            '',
        ]);

        return response($body, 200, ['Content-Type' => 'text/plain']);
    }

    /**
     * The 1200×630 social-share image for a site, cached by version.
     */
    public function ogImage(string $slug): Response
    {
        $site = Site::query()->where('slug', $slug)->firstOrFail();

        $png = Cache::remember(
            "og:{$site->id}:{$site->version()}",
            now()->addDay(),
            fn (): string => OgImage::forSite($site),
        );

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * The landing page's own 1200×630 share card (og:image), cached hard — it
     * only changes when the design version below is bumped.
     */
    public function homeOg(): Response
    {
        $png = Cache::remember('og:home:v1', now()->addDays(30), fn (): string => OgImage::forHome());

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }

    /**
     * The product's brand favicon: a teal rounded tile with a white dot.
     */
    public function homeFavicon(): Response
    {
        $accent = Site::DEFAULT_ACCENT;

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">'
            .'<rect width="32" height="32" rx="7" fill="'.$accent.'"/>'
            .'<circle cx="16" cy="16" r="6" fill="#ffffff"/></svg>';

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }

    /**
     * The apple-touch-icon: the same brand mark as a 180×180 PNG.
     */
    public function homeIcon(): Response
    {
        $png = Cache::remember('icon:home:v1', now()->addDays(30), fn (): string => OgImage::homeIcon());

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }

    public function favicon(string $slug): Response|StreamedResponse
    {
        $site = Site::query()->where('slug', $slug)->firstOrFail();

        // An uploaded mark wins over the generated dot.
        $icon = $site->asset(SiteAsset::FAVICON);

        if ($icon !== null && Storage::disk(SiteAsset::DISK)->exists($icon->disk_path)) {
            return Storage::disk(SiteAsset::DISK)->response($icon->disk_path, 'favicon', [
                'Content-Type' => $icon->mime,
                'Cache-Control' => 'public, max-age=86400',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        $accent = $site->accent();

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">'
            .'<rect width="32" height="32" rx="7" fill="'.$accent.'"/>'
            .'<circle cx="16" cy="16" r="6" fill="#ffffff"/></svg>';

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
