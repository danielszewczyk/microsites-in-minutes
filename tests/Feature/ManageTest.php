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

    public function test_manage_header_shows_a_persistent_url_with_a_copy_button_and_no_email_share(): void
    {
        $site = Site::factory()->withToken('secret-token')->create();

        $this->withCookie($site->cookieName(), 'secret-token')
            ->get(route('microsite.manage', $site->slug))
            ->assertOk()
            // The live URL is persistent in the header, with a Copy URL button (#13).
            ->assertSee('id="site-url"', false)
            ->assertSee('Copy URL')
            ->assertSee($site->url(), false)
            // Email-share is gone (#12) — no "Email the link" and no mailto: links.
            ->assertDontSee('Email the link')
            ->assertDontSee('mailto:', false)
            // The create-only copy button is absent on an ordinary visit (no 'created' flash).
            ->assertDontSee('id="created-copy"', false);
    }

    public function test_manage_documents_the_import_api_and_hides_the_token_behind_reveal(): void
    {
        $site = Site::factory()->withToken('secret-token')->create();

        $this->withCookie($site->cookieName(), 'secret-token')
            ->get(route('microsite.manage', $site->slug))
            ->assertOk()
            ->assertSee('Import API')
            ->assertSee(route('api.entries.store', $site->slug, absolute: true), false)
            ->assertSee('curl -X POST', false)
            ->assertSee('Rate limit')
            // The token is no longer printed in plaintext on screen...
            ->assertDontSee('Authorization: Bearer secret-token', false)
            // ...it sits behind a Reveal toggle, in a data attribute for the JS.
            ->assertSee('Reveal token')
            ->assertSee('data-token="secret-token"', false);
    }

    public function test_api_token_share_link_reveals_the_credentials_once(): void
    {
        $site = Site::factory()->withToken('secret-token')->create();

        $this->withCookie($site->cookieName(), 'secret-token')
            ->post(route('microsite.api-token.share', $site->slug))
            ->assertRedirect()
            ->assertSessionHas('api_share_url');

        $url = session('api_share_url');
        $this->assertIsString($url);

        // Opening the link (GET) shows a confirm page and does NOT reveal or
        // consume the token — so a link-preview bot's fetch can't burn it.
        $this->get($url)
            ->assertOk()
            ->assertSee('Reveal credentials')
            ->assertDontSee('secret-token');

        $id = basename(parse_url($url, PHP_URL_PATH));

        // Confirming (POST) reveals the endpoint and token, once.
        $this->post(route('api-token.reveal.confirm', $id))
            ->assertOk()
            ->assertSee('secret-token')
            ->assertSee(route('api.entries.store', $site->slug, absolute: true), false);

        // The link is single-use: confirming again is spent, and the GET
        // confirm page now reports the link as expired.
        $this->post(route('api-token.reveal.confirm', $id))
            ->assertOk()
            ->assertSee('expired')
            ->assertDontSee('secret-token');

        $this->get($url)->assertOk()->assertSee('expired');
    }

    public function test_api_token_share_needs_the_manage_token(): void
    {
        $site = Site::factory()->withToken('secret-token')->create();

        $this->post(route('microsite.api-token.share', $site->slug))->assertForbidden();
    }

    public function test_an_unknown_share_link_shows_the_spent_state(): void
    {
        $id = str_repeat('a', 40);

        $this->get(route('api-token.reveal', $id))->assertOk()->assertSee('expired');
        $this->post(route('api-token.reveal.confirm', $id))->assertOk()->assertSee('expired');
    }

    public function test_google_search_preview_shows_a_favicon_with_a_default_fallback(): void
    {
        $site = Site::factory()->withToken('secret-token')->create();

        $this->withCookie($site->cookieName(), 'secret-token')
            ->get(route('microsite.manage', $site->slug))
            ->assertOk()
            ->assertSee('Google search preview')
            ->assertSee(route('microsite.favicon', $site->slug), false);

        // The favicon endpoint returns a generated default mark when none is
        // uploaded, so the preview always has an icon to show.
        $this->get(route('microsite.favicon', $site->slug))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml');
    }

    public function test_free_tier_storage_allowance_is_ten_megabytes(): void
    {
        $this->assertSame(10, config('plans.tiers.free.storage_mb'));

        $site = Site::factory()->withToken('secret-token')->create();

        $this->withCookie($site->cookieName(), 'secret-token')
            ->get(route('microsite.manage', $site->slug))
            ->assertOk()
            ->assertSee('Storage')
            ->assertSee('10 MB');
    }

    public function test_the_live_stats_endpoint_is_owner_gated_and_returns_counts(): void
    {
        $site = Site::factory()->withToken('secret-token')->create(['view_count' => 10, 'entry_count' => 3]);

        $this->get(route('microsite.stats', $site->slug))->assertForbidden();

        $this->withCookie($site->cookieName(), 'secret-token')
            ->get(route('microsite.stats', $site->slug), ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJson(['views' => 10, 'entries' => 3, 'range' => 'month'])
            ->assertJsonStructure(['views', 'entries', 'conversion', 'dwell', 'live', 'range', 'caption', 'series']);
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

    public function test_hero_overlay_saves_and_clears_from_the_manage_form(): void
    {
        $site = Site::factory()->withToken('secret-token')->create();
        $form = [
            'business_name' => $site->inputs['business_name'],
            'prompt' => 'A completely rewritten description for the salon with a weekend special offer now.',
            'type' => 'standard',
        ];

        $this->withCookie($site->cookieName(), 'secret-token')
            ->post(route('microsite.regenerate', $site->slug), [...$form, 'hero_overlay' => '1'])
            ->assertRedirect();
        $this->assertTrue($site->refresh()->heroOverlay());

        // An unticked checkbox sends no key at all, so the saved value must still clear.
        $this->withCookie($site->cookieName(), 'secret-token')
            ->post(route('microsite.regenerate', $site->slug), $form)
            ->assertRedirect();
        $this->assertFalse($site->refresh()->heroOverlay());
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
