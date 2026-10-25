<?php

namespace Tests\Feature;

use App\Models\Owner;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_shows_the_prompt_box(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Microsites in Minutes')
            ->assertSee('name="prompt"', false);
    }

    public function test_a_guest_is_prompted_to_log_in_to_publish(): void
    {
        $this->get('/')->assertOk()->assertSee('Log in or sign up to publish');

        // The publish endpoint itself is gated.
        $this->post('/sites', ['business_name' => 'X', 'prompt' => 'A promo page for a shop that wants more customers.', 'type' => 'standard'])
            ->assertRedirect(route('login'));
    }

    public function test_publishing_a_standard_site_creates_the_site_and_redirects_to_manage(): void
    {
        $owner = Owner::factory()->create();

        $response = $this->actingAs($owner)->post('/sites', [
            'business_name' => 'Paul\'s Parlor',
            'prompt' => 'A friendly neighbourhood hair salon that wants more weekday bookings.',
            'type' => 'standard',
        ]);

        $site = Site::query()->firstOrFail();
        $response->assertRedirect(route('microsite.manage', $site->slug));
        $response->assertSessionHas('created');

        $this->assertSame($owner->id, $site->owner_id);
        $this->assertSame('published', $site->status);
        $this->assertNotEmpty($site->content['hero']['headline']);
    }

    public function test_an_unverified_owner_cannot_publish(): void
    {
        $owner = Owner::factory()->unverified()->create();

        $this->actingAs($owner)->post('/sites', [
            'business_name' => 'Paul\'s Parlor',
            'prompt' => 'A friendly neighbourhood hair salon that wants more weekday bookings.',
            'type' => 'standard',
        ])->assertRedirect(route('verification.notice'));

        $this->assertDatabaseCount('sites', 0);
    }

    public function test_sweepstakes_requires_a_prize(): void
    {
        $this->actingAs(Owner::factory()->create())->post('/sites', [
            'business_name' => 'Giveaway Co',
            'prompt' => 'A giveaway for our grand opening this month.',
            'type' => 'sweepstakes',
        ])->assertSessionHasErrors('prize');

        $this->assertDatabaseCount('sites', 0);
    }

    public function test_the_builder_drops_the_type_toggle_for_optional_add_ons(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertDontSee('What kind of page?')
            ->assertSee('Add-ons')
            ->assertSee('name="addon"', false);
    }

    public function test_with_no_add_on_the_page_defaults_to_standard(): void
    {
        $owner = Owner::factory()->create();

        $this->actingAs($owner)->post('/sites', [
            'business_name' => 'Plain Co',
            'prompt' => 'A simple landing page for a shop that wants more weekday customers.',
        ])->assertRedirect();

        $this->assertSame(Site::TYPE_STANDARD, Site::query()->firstOrFail()->type);
    }

    public function test_the_sweepstakes_add_on_derives_the_type_and_requires_a_prize(): void
    {
        $owner = Owner::factory()->create();

        // The add-on is on but there is no prize — the sweepstakes rule fires,
        // which proves the type was derived from the add-on, not a type field.
        $this->actingAs($owner)->post('/sites', [
            'business_name' => 'Giveaway Co',
            'prompt' => 'A giveaway for our grand opening this month in the neighbourhood.',
            'addon' => 'sweepstakes',
        ])->assertSessionHasErrors('prize');
        $this->assertDatabaseCount('sites', 0);

        $this->actingAs($owner)->post('/sites', [
            'business_name' => 'Giveaway Co',
            'prompt' => 'A giveaway for our grand opening this month in the neighbourhood.',
            'addon' => 'sweepstakes',
            'prize' => 'A $250 gift basket of our products.',
        ])->assertRedirect();
        $this->assertSame(Site::TYPE_SWEEPSTAKES, Site::query()->firstOrFail()->type);
    }

    public function test_the_event_add_on_derives_the_event_type(): void
    {
        $owner = Owner::factory()->create();

        $this->actingAs($owner)->post('/sites', [
            'business_name' => 'Gala Co',
            'prompt' => 'An invitation page for our annual charity gala with RSVPs this autumn season.',
            'addon' => 'event',
            'event_date' => now()->addMonth()->toDateString(),
        ])->assertRedirect();

        $this->assertSame(Site::TYPE_EVENT, Site::query()->firstOrFail()->type);
    }

    public function test_free_plan_allows_only_one_site(): void
    {
        $owner = Owner::factory()->create(['plan' => 'free']);
        Site::factory()->for($owner)->create();

        $this->actingAs($owner)->post('/sites', [
            'business_name' => 'Second Site',
            'prompt' => 'A second promo page for the same owner, which should be blocked.',
            'type' => 'standard',
        ])->assertSessionHasErrors('prompt');

        $this->assertDatabaseCount('sites', 1);
    }
}
