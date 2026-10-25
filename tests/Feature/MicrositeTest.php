<?php

namespace Tests\Feature;

use App\Models\Owner;
use App\Models\Site;
use App\Sites\SiteComposer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MicrositeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function publish(array $overrides = [], ?Owner $owner = null): Site
    {
        $owner ??= Owner::factory()->business()->create();

        return app(SiteComposer::class)->publish([
            'business_name' => 'Paul\'s Parlor',
            'prompt' => 'A friendly neighbourhood hair salon that wants more weekday bookings.',
            'type' => 'standard',
            ...$overrides,
        ], $owner)['site'];
    }

    public function test_published_page_renders_with_seo_tags(): void
    {
        $site = $this->publish();

        $this->get($site->url())
            ->assertOk()
            ->assertSee('<title>', false)
            ->assertSee('<meta name="description"', false)
            ->assertSee('rel="canonical"', false)
            ->assertSee('property="og:title"', false)
            ->assertSee('index, follow');
    }

    public function test_output_is_escaped_against_injection(): void
    {
        $site = $this->publish([
            'business_name' => 'Hack <script>alert(1)</script>',
            'prompt' => 'Trying <script>alert(2)</script> to break out of the page markup here.',
        ]);

        $html = $this->get($site->url())->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('<script>alert(2)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_invalid_accent_falls_back_to_default(): void
    {
        $site = $this->publish();
        $site->update(['settings' => [...$site->settings, 'accent_color' => 'javascript:evil']]);

        $this->get($site->url())
            ->assertOk()
            ->assertSee('--accent: '.Site::DEFAULT_ACCENT, false)
            ->assertDontSee('javascript:evil');
    }

    public function test_version_endpoint_returns_the_current_version(): void
    {
        $site = $this->publish();

        $this->getJson($site->url().'/version')
            ->assertOk()
            ->assertJson(['version' => $site->version()]);
    }

    public function test_sweepstakes_has_a_rules_page_but_standard_does_not(): void
    {
        $sweeps = $this->publish([
            'type' => 'sweepstakes',
            'prize' => 'a whitening kit',
            'prize_value' => '$250',
        ]);
        $standard = $this->publish();

        $this->get(route('microsite.rules', $sweeps->slug))
            ->assertOk()
            ->assertSee('Official Rules')
            ->assertSee('NO PURCHASE NECESSARY')
            ->assertSee('alternate method of entry', false);

        $this->get(route('microsite.rules', $standard->slug))->assertNotFound();
    }

    public function test_ga4_tag_only_renders_for_a_plan_with_analytics(): void
    {
        $free = $this->publish(['ga4_id' => 'G-ABCDEF12'], Owner::factory()->create());
        $this->get($free->url())->assertDontSee('googletagmanager.com');

        $site = $this->publish([
            'business_name' => 'Pro Shop',
            'ga4_id' => 'G-ABCDEF12',
        ], Owner::factory()->pro()->create());

        $this->get($site->url())->assertSee('googletagmanager.com/gtag/js?id=G-ABCDEF12', false);
    }

    public function test_view_is_counted(): void
    {
        $site = $this->publish();

        $this->get($site->url())->assertOk();

        $this->assertSame(1, $site->fresh()->view_count);
        $this->assertDatabaseHas('site_stats', ['site_id' => $site->id, 'views' => 1]);
    }
}
