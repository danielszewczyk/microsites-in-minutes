<?php

namespace Tests\Feature;

use App\Models\Owner;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QaFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_view_does_not_change_the_version(): void
    {
        $site = Site::factory()->create();
        $before = $site->version();

        $this->get($site->url())->assertOk();
        $this->get($site->url())->assertOk();

        // View counting must not change the version (which would loop the
        // self-refresh and bust the cache on every visit).
        $this->assertSame($before, $site->fresh()->version());
        $this->assertGreaterThanOrEqual(2, $site->fresh()->view_count);
    }

    public function test_public_page_sends_security_headers(): void
    {
        $site = Site::factory()->create();

        $this->get($site->url())
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_reserved_hosts_cannot_be_claimed(): void
    {
        config(['domains.reserved' => ['micrositesinminutes.com', 'localhost']]);
        $site = Site::factory()->for(Owner::factory()->business())->withToken('tok')->create();

        $this->withCookie($site->cookieName(), 'tok')
            ->post(route('microsite.domain.buy', $site->slug), ['domain' => 'www.micrositesinminutes.com'])
            ->assertSessionHasErrors('domain');

        $this->assertDatabaseCount('domains', 0);
    }

    public function test_draft_rules_page_is_noindex(): void
    {
        $site = Site::factory()->sweepstakes()->create(['status' => Site::STATUS_DRAFT]);

        $this->get(route('microsite.rules', $site->slug))
            ->assertOk()
            ->assertSee('noindex, nofollow', false);
    }

    public function test_owner_can_publish_a_draft(): void
    {
        $site = Site::factory()->withToken('tok')->create(['status' => Site::STATUS_DRAFT]);

        $this->withCookie($site->cookieName(), 'tok')
            ->post(route('microsite.publish', $site->slug))
            ->assertRedirect();

        $this->assertSame(Site::STATUS_PUBLISHED, $site->fresh()->status);

        // And now it accepts entries.
        $this->postJson($site->url().'/enter', ['name' => 'Jamie', 'email' => 'j@example.com'])->assertOk();
    }

    public function test_publishing_a_draft_needs_the_token(): void
    {
        $site = Site::factory()->create(['status' => Site::STATUS_DRAFT]);

        $this->post(route('microsite.publish', $site->slug))->assertForbidden();
        $this->assertSame(Site::STATUS_DRAFT, $site->fresh()->status);
    }
}
