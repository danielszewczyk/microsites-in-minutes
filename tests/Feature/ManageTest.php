<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Models\SiteEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManageTest extends TestCase
{
    use RefreshDatabase;

    public function test_manage_needs_the_token(): void
    {
        $site = Site::factory()->withToken('secret-token')->create();

        $this->get(route('microsite.manage', $site->slug))->assertForbidden();

        $this->withCookie($site->cookieName(), 'secret-token')
            ->get(route('microsite.manage', $site->slug))
            ->assertOk()
            ->assertSee('Edit &amp; regenerate', false);
    }

    public function test_manage_offers_sharing_the_link_by_email(): void
    {
        $site = Site::factory()->withToken('secret-token')->create();

        $this->withCookie($site->cookieName(), 'secret-token')
            ->get(route('microsite.manage', $site->slug))
            ->assertOk()
            ->assertSee('Email the link')
            ->assertSee('mailto:?subject=', false)
            // The create-only copy button is absent on an ordinary visit (no 'created' flash).
            ->assertDontSee('id="created-copy"', false);
    }

    public function test_manage_documents_the_import_api_with_the_real_endpoint_and_token(): void
    {
        $site = Site::factory()->withToken('secret-token')->create();

        $this->withCookie($site->cookieName(), 'secret-token')
            ->get(route('microsite.manage', $site->slug))
            ->assertOk()
            ->assertSee('Import API')
            ->assertSee(route('api.entries.store', $site->slug, absolute: true), false)
            ->assertSee('Authorization: Bearer secret-token', false)
            ->assertSee('curl -X POST', false)
            ->assertSee('Rate limit');
    }

    public function test_the_live_stats_endpoint_is_owner_gated_and_returns_counts(): void
    {
        $site = Site::factory()->withToken('secret-token')->create(['view_count' => 10, 'entry_count' => 3]);

        $this->get(route('microsite.stats', $site->slug))->assertForbidden();

        $this->withCookie($site->cookieName(), 'secret-token')
            ->get(route('microsite.stats', $site->slug), ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJson(['views' => 10, 'entries' => 3])
            ->assertJsonStructure(['views', 'entries', 'conversion', 'dwell', 'live', 'days']);
    }

    public function test_a_recent_viewer_shows_as_live_on_the_dashboard(): void
    {
        $site = Site::factory()->withToken('secret-token')->create();

        // A visitor's page beats via the version poll with its per-tab id.
        $this->get(route('microsite.version', $site->slug).'?v=tabABC123')->assertOk();

        $this->withCookie($site->cookieName(), 'secret-token')
            ->get(route('microsite.stats', $site->slug), ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('live', 1);
    }

    public function test_share_link_token_sets_a_cookie_and_redirects(): void
    {
        $site = Site::factory()->withToken('secret-token')->create();

        $this->get(route('microsite.manage', $site->slug).'?t=secret-token')
            ->assertRedirect(route('microsite.manage', $site->slug))
            ->assertCookie($site->cookieName(), 'secret-token');
    }

    public function test_regenerate_updates_content_in_place(): void
    {
        $site = Site::factory()->withToken('secret-token')->create();
        $before = $site->version();

        $this->withCookie($site->cookieName(), 'secret-token')
            ->post(route('microsite.regenerate', $site->slug), [
                'business_name' => 'Paul\'s New Parlor',
                'prompt' => 'A completely rewritten description for the salon with a weekend special offer now.',
                'type' => 'standard',
            ])->assertRedirect();

        $site->refresh();
        $this->assertSame('Paul\'s New Parlor', $site->inputs['business_name']);
        $this->assertNotSame($before, $site->version());
    }

    public function test_regenerate_is_rejected_without_the_token(): void
    {
        $site = Site::factory()->withToken('secret-token')->create();

        $this->post(route('microsite.regenerate', $site->slug), [
            'business_name' => 'Hijacked',
            'prompt' => 'An attacker trying to rewrite a site they do not own at all here.',
            'type' => 'standard',
        ])->assertForbidden();
    }

    public function test_entries_export_as_csv(): void
    {
        $site = Site::factory()->withToken('secret-token')->create();
        SiteEntry::factory()->for($site)->create(['name' => 'Jamie Lee', 'email' => 'jamie@example.com']);

        $response = $this->withCookie($site->cookieName(), 'secret-token')
            ->get(route('microsite.entries.export', $site->slug));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('Jamie Lee', $response->streamedContent());
    }

    public function test_the_created_banner_offers_a_copy_link_button(): void
    {
        $site = Site::factory()->withToken('secret-token')->create();
        $url = $site->url();

        $this->withCookie($site->cookieName(), 'secret-token')
            ->withSession(['created' => [
                'url' => $url,
                'manage_url' => route('microsite.manage', $site->slug).'?t=secret-token',
                'build_ms' => 1200,
                'build_cents' => 0,
            ]])
            ->get(route('microsite.manage', $site->slug))
            ->assertOk()
            ->assertSee('Your site is live')
            ->assertSee('id="created-copy"', false)
            ->assertSee('Copy link')
            ->assertSee('data-copy="'.e($url).'"', false);
    }
}
