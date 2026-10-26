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
            ->assertSee('mailto:?subject=', false);
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
}
