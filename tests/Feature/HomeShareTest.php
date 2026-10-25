<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeShareTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_home_og_image_renders_as_a_1200x630_png(): void
    {
        $response = $this->get(route('home.og'));
        $response->assertOk()->assertHeader('Content-Type', 'image/png');

        $png = $response->getContent();
        $this->assertSame("\x89PNG", substr($png, 0, 4));
        [$width, $height] = getimagesizefromstring($png);
        $this->assertSame([1200, 630], [$width, $height]);
    }

    public function test_the_touch_icon_renders_as_png_and_the_favicon_as_svg(): void
    {
        $this->get(route('home.icon'))->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get(route('home.favicon'))->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
    }

    public function test_the_landing_page_declares_full_share_and_icon_tags(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('property="og:image"', false)
            ->assertSee('property="og:image:width" content="1200"', false)
            ->assertSee('summary_large_image', false)
            ->assertSee(route('home.og', absolute: true), false)
            ->assertSee('rel="apple-touch-icon"', false)
            ->assertSee(route('home.favicon', absolute: true), false);
    }
}
