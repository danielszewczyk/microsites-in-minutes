<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Owner;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VanityDomainTest extends TestCase
{
    use RefreshDatabase;

    protected function paidSite(): Site
    {
        return Site::factory()->for(Owner::factory()->business())->withToken('tok')->create();
    }

    public function test_search_reports_availability_and_the_price(): void
    {
        $site = $this->paidSite();

        $this->withCookie($site->cookieName(), 'tok')
            ->post(route('microsite.domain.search', $site->slug), ['domain' => 'https://SubmitYourSmile.com/path'])
            ->assertSessionHas('domain_search');

        $search = session('domain_search');
        $this->assertSame('submityoursmile.com', $search['domain']);
        $this->assertTrue($search['available']);
        $this->assertSame(2_500, $search['price_cents']);
    }

    public function test_buying_creates_a_pending_domain(): void
    {
        $site = $this->paidSite();

        $this->withCookie($site->cookieName(), 'tok')
            ->post(route('microsite.domain.buy', $site->slug), ['domain' => 'submityoursmile.com'])
            ->assertRedirect();

        $this->assertDatabaseHas('domains', [
            'site_id' => $site->id, 'domain' => 'submityoursmile.com', 'status' => Domain::PENDING_DNS, 'price_cents' => 2_500,
        ]);
    }

    public function test_free_plan_cannot_buy_a_domain(): void
    {
        $site = Site::factory()->for(Owner::factory()->create(['plan' => 'free']))->withToken('tok')->create();

        $this->withCookie($site->cookieName(), 'tok')
            ->post(route('microsite.domain.buy', $site->slug), ['domain' => 'example.com'])
            ->assertForbidden();

        $this->assertDatabaseCount('domains', 0);
    }

    public function test_activate_makes_it_live(): void
    {
        $site = $this->paidSite();
        $site->domains()->create(['owner_id' => $site->owner_id, 'domain' => 'submityoursmile.com', 'status' => Domain::PENDING_DNS, 'price_cents' => 2_500]);

        $this->withCookie($site->cookieName(), 'tok')
            ->post(route('microsite.domain.activate', $site->slug))
            ->assertRedirect();

        $this->assertSame(Domain::ACTIVE, $site->domains()->first()->status);
    }

    public function test_the_site_is_served_on_its_active_vanity_domain(): void
    {
        $site = $this->paidSite();
        $site->domains()->create(['owner_id' => $site->owner_id, 'domain' => 'submityoursmile.test', 'status' => Domain::ACTIVE, 'price_cents' => 2_500, 'verified_at' => now()]);

        $this->get('http://submityoursmile.test/')
            ->assertOk()
            ->assertSee($site->content['hero']['headline']);
    }

    public function test_our_own_host_still_shows_the_builder(): void
    {
        $this->paidSite();

        $this->get('/')->assertOk()->assertSee('Generate my site');
    }

    public function test_a_taken_domain_is_not_available(): void
    {
        $site = $this->paidSite();
        Site::factory()->create()->domains()->create(['owner_id' => 1, 'domain' => 'taken.com', 'status' => Domain::ACTIVE, 'price_cents' => 2_500]);

        $this->withCookie($site->cookieName(), 'tok')
            ->post(route('microsite.domain.search', $site->slug), ['domain' => 'taken.com']);

        $this->assertFalse(session('domain_search')['available']);
    }
}
