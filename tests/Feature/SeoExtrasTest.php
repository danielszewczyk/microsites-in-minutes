<?php

namespace Tests\Feature;

use App\Models\Owner;
use App\Models\Site;
use App\Models\SiteAsset;
use App\Sites\SiteComposer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SeoExtrasTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_og_image_renders_as_png(): void
    {
        $site = Site::factory()->create();

        $response = $this->get(route('microsite.og', $site->slug));
        $response->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertSame("\x89PNG", substr($response->getContent(), 0, 4));
    }

    public function test_the_page_declares_og_and_twitter_image(): void
    {
        $site = Site::factory()->create();

        $this->get($site->url())
            ->assertSee('property="og:image"', false)
            ->assertSee('summary_large_image', false)
            ->assertSee(route('microsite.og', $site->slug, absolute: true), false);
    }

    public function test_an_uploaded_favicon_is_served_over_the_generated_one(): void
    {
        Storage::fake(SiteAsset::DISK);
        $site = Site::factory()->create();
        $path = $site->assetFolder().'/favicon.png';
        Storage::disk(SiteAsset::DISK)->put($path, 'x');
        $site->assets()->create(['slot' => SiteAsset::FAVICON, 'disk_path' => $path, 'original_name' => 'mark.png', 'mime' => 'image/png', 'size' => 1]);

        $this->get(route('microsite.favicon', $site->slug))->assertOk()->assertHeader('Content-Type', 'image/png');

        // A site without an upload gets the generated SVG dot.
        $other = Site::factory()->create();
        $this->get(route('microsite.favicon', $other->slug))->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
    }

    public function test_owner_can_override_the_title_and_description(): void
    {
        $site = app(SiteComposer::class)->publish([
            'business_name' => 'Paul\'s Parlor',
            'prompt' => 'A neighbourhood salon that wants more weekday bookings this autumn season.',
            'type' => 'standard',
            'seo_title' => 'Best Salon in Town',
            'seo_description' => 'Book your cut today — weekday specials all month.',
        ], Owner::factory()->create())['site'];

        $this->assertSame('Best Salon in Town', $site->content['meta']['title']);
        $this->assertSame('Book your cut today — weekday specials all month.', $site->content['meta']['description']);

        $this->get($site->url())->assertSee('Best Salon in Town', false);
    }

    public function test_time_on_page_is_recorded_and_averaged(): void
    {
        $site = Site::factory()->create();

        $this->postJson($site->url().'/ping', ['seconds' => 30])->assertNoContent();
        $this->postJson($site->url().'/ping', ['seconds' => 50])->assertNoContent();

        $site->refresh();
        $this->assertSame(2, (int) $site->dwell_count);
        $this->assertSame(40, $site->avgDwellSeconds());
    }

    public function test_absurd_dwell_values_are_ignored(): void
    {
        $site = Site::factory()->create();

        $this->postJson($site->url().'/ping', ['seconds' => 999999])->assertNoContent();
        $this->postJson($site->url().'/ping', ['seconds' => 0])->assertNoContent();

        $this->assertSame(0, (int) $site->fresh()->dwell_count);
    }
}
