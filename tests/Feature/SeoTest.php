<?php

namespace Tests\Feature;

use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_lists_published_sites(): void
    {
        $site = Site::factory()->create();

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml')
            ->assertSee($site->url(), false)
            ->assertSee('<urlset', false);
    }

    public function test_robots_points_to_the_sitemap_and_blocks_manage(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('Sitemap: '.route('sitemap'))
            ->assertSee('Disallow: /s/*/manage');
    }

    public function test_per_site_favicon_uses_the_accent_colour(): void
    {
        $site = Site::factory()->create();
        $site->update(['settings' => [...$site->settings, 'accent_color' => '#123abc']]);

        $this->get(route('microsite.favicon', $site->slug))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml')
            ->assertSee('#123abc', false);
    }
}
