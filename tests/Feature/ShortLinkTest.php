<?php

namespace Tests\Feature;

use App\Models\ShortLink;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShortLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_a_utm_tagged_short_link(): void
    {
        $site = Site::factory()->withToken('tok')->create();

        $this->withCookie($site->cookieName(), 'tok')
            ->post(route('microsite.links.store', $site->slug), [
                'source' => 'Facebook', 'medium' => 'social', 'campaign' => 'Fall Launch', 'label' => 'FB post',
            ])->assertRedirect();

        $link = $site->shortLinks()->firstOrFail();
        $this->assertSame('facebook', $link->source);
        $this->assertSame('fall launch', $link->campaign);
        $this->assertStringContainsString('utm_source=facebook', $link->targetUrl());
        $this->assertStringContainsString('utm_campaign=fall+launch', $link->targetUrl());
    }

    public function test_creating_a_link_needs_the_token(): void
    {
        $site = Site::factory()->withToken('tok')->create();

        $this->post(route('microsite.links.store', $site->slug), ['source' => 'facebook'])->assertForbidden();
        $this->assertDatabaseCount('short_links', 0);
    }

    public function test_the_redirect_counts_the_click_and_forwards_with_tags(): void
    {
        $site = Site::factory()->create();
        $link = $site->shortLinks()->create([
            'owner_id' => $site->owner_id, 'code' => 'abc1234', 'source' => 'google', 'campaign' => 'spring',
        ]);

        $this->get(route('link.redirect', 'abc1234'))
            ->assertRedirect($site->url().'?utm_source=google&utm_campaign=spring');

        $this->assertSame(1, $link->fresh()->clicks);
    }

    public function test_an_unknown_code_is_not_found(): void
    {
        $this->get(route('link.redirect', 'missing'))->assertNotFound();
    }

    public function test_owner_can_delete_a_link(): void
    {
        $site = Site::factory()->withToken('tok')->create();
        $link = $site->shortLinks()->create(['owner_id' => $site->owner_id, 'code' => ShortLink::freeCode()]);

        $this->withCookie($site->cookieName(), 'tok')
            ->post(route('microsite.links.destroy', [$site->slug, $link]))
            ->assertRedirect();

        $this->assertDatabaseCount('short_links', 0);
    }

    public function test_a_link_redirect_is_attributed_in_reporting(): void
    {
        $site = Site::factory()->create();
        $site->shortLinks()->create(['owner_id' => $site->owner_id, 'code' => 'tiktok1', 'source' => 'tiktok']);

        // Follow the short link to the site, carrying its utm_source.
        $this->get(route('link.redirect', 'tiktok1'));
        $this->get($site->url().'?utm_source=tiktok')->assertOk();

        $this->assertDatabaseHas('site_breakdowns', [
            'site_id' => $site->id, 'dimension' => 'source', 'key' => 'tiktok',
        ]);
    }
}
