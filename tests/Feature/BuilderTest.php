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
            ->assertSee('name="prompt"', false)
            ->assertSee('Generate my site');
    }

    public function test_publishing_a_standard_site_creates_the_site_and_redirects_to_manage(): void
    {
        $response = $this->post('/sites', [
            'owner_email' => 'pat@example.com',
            'business_name' => 'Paul\'s Parlor',
            'prompt' => 'A friendly neighbourhood hair salon that wants more weekday bookings.',
            'type' => 'standard',
        ]);

        $site = Site::query()->firstOrFail();
        $response->assertRedirect(route('microsite.manage', $site->slug));
        $response->assertSessionHas('created');

        $this->assertDatabaseHas('owners', ['email' => 'pat@example.com']);
        $this->assertSame('published', $site->status);
        $this->assertNotEmpty($site->content['hero']['headline']);
    }

    public function test_sweepstakes_requires_a_prize(): void
    {
        $this->post('/sites', [
            'owner_email' => 'pat@example.com',
            'business_name' => 'Giveaway Co',
            'prompt' => 'A giveaway for our grand opening this month.',
            'type' => 'sweepstakes',
        ])->assertSessionHasErrors('prize');

        $this->assertDatabaseCount('sites', 0);
    }

    public function test_free_plan_allows_only_one_site(): void
    {
        $owner = Owner::factory()->create(['email' => 'pat@example.com', 'plan' => 'free']);
        Site::factory()->for($owner)->create();

        $this->post('/sites', [
            'owner_email' => 'pat@example.com',
            'business_name' => 'Second Site',
            'prompt' => 'A second promo page for the same owner, which should be blocked.',
            'type' => 'standard',
        ])->assertSessionHasErrors('prompt');

        $this->assertDatabaseCount('sites', 1);
    }
}
