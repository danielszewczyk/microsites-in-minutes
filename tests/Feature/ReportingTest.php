<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Models\SiteBreakdown;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_view_is_attributed_to_its_source_and_device(): void
    {
        $site = Site::factory()->create();

        $this->get($site->url().'?utm_source=google&utm_campaign=fall')->assertOk();

        $this->assertDatabaseHas('site_breakdowns', [
            'site_id' => $site->id, 'dimension' => SiteBreakdown::SOURCE, 'key' => 'google', 'views' => 1,
        ]);
        $this->assertDatabaseHas('site_breakdowns', [
            'site_id' => $site->id, 'dimension' => SiteBreakdown::CAMPAIGN, 'key' => 'fall', 'views' => 1,
        ]);
        $this->assertDatabaseHas('site_breakdowns', [
            'site_id' => $site->id, 'dimension' => SiteBreakdown::DEVICE, 'key' => 'desktop', 'views' => 1,
        ]);
    }

    public function test_a_referrer_becomes_the_source_when_no_utm(): void
    {
        $site = Site::factory()->create();

        $this->get($site->url(), ['referer' => 'https://m.facebook.com/some/post'])->assertOk();

        $this->assertDatabaseHas('site_breakdowns', [
            'site_id' => $site->id, 'dimension' => SiteBreakdown::SOURCE, 'key' => 'facebook',
        ]);
    }

    public function test_direct_when_no_source_or_referrer(): void
    {
        $site = Site::factory()->create();

        $this->get($site->url())->assertOk();

        $this->assertDatabaseHas('site_breakdowns', [
            'site_id' => $site->id, 'dimension' => SiteBreakdown::SOURCE, 'key' => 'direct',
        ]);
    }

    public function test_an_entry_is_attributed_to_its_source(): void
    {
        $site = Site::factory()->create();

        $this->postJson($site->url().'/enter', [
            'name' => 'Jamie',
            'email' => 'jamie@example.com',
            'utm_source' => 'google',
            'utm_campaign' => 'fall',
        ])->assertOk();

        $this->assertDatabaseHas('site_entries', ['site_id' => $site->id, 'source' => 'google', 'campaign' => 'fall']);
        $this->assertDatabaseHas('site_breakdowns', [
            'site_id' => $site->id, 'dimension' => SiteBreakdown::SOURCE, 'key' => 'google', 'entries' => 1,
        ]);
    }

    public function test_dashboard_shows_the_source_breakdown(): void
    {
        $site = Site::factory()->withToken('tok')->create();
        $this->get($site->url().'?utm_source=google')->assertOk();

        $this->withCookie($site->cookieName(), 'tok')
            ->get(route('microsite.manage', $site->slug))
            ->assertOk()
            ->assertSee("Where it's coming from", false)
            ->assertSee('google');
    }

    public function test_csv_export_includes_the_source_column(): void
    {
        $site = Site::factory()->withToken('tok')->create();
        $this->postJson($site->url().'/enter', ['name' => 'Jamie', 'email' => 'j@example.com', 'utm_source' => 'bing']);

        $csv = $this->withCookie($site->cookieName(), 'tok')
            ->get(route('microsite.entries.export', $site->slug))
            ->streamedContent();

        $this->assertStringContainsString('Source', $csv);
        $this->assertStringContainsString('bing', $csv);
    }
}
