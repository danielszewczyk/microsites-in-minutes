<?php

namespace App\Http\Controllers;

use App\Models\Site;
use Illuminate\Http\Response;

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

    public function favicon(string $slug): Response
    {
        $site = Site::query()->where('slug', $slug)->firstOrFail();
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
