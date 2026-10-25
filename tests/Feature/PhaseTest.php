<?php

namespace Tests\Feature;

use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_before_go_live_shows_the_coming_soon_page(): void
    {
        $site = Site::factory()->create();
        $site->update(['settings' => [...$site->settings, 'go_live_at' => now()->addDays(3)->toIso8601String()]]);

        $this->assertSame(Site::PHASE_COMING_SOON, $site->phase());
        $this->get($site->url())->assertOk()->assertSee('Coming soon')->assertSee('Notify me');
    }

    public function test_after_end_shows_the_ended_page(): void
    {
        $site = Site::factory()->create();
        $site->update(['settings' => [...$site->settings, 'ends_at' => now()->subDay()->toIso8601String()]]);

        $this->assertSame(Site::PHASE_ENDED, $site->phase());
        $this->get($site->url())->assertOk()->assertSee('This has ended');
    }

    public function test_between_the_triggers_shows_the_live_page(): void
    {
        $site = Site::factory()->create();
        $site->update(['settings' => [...$site->settings, 'go_live_at' => now()->subDay()->toIso8601String(), 'ends_at' => now()->addDay()->toIso8601String()]]);

        $this->assertSame(Site::PHASE_LIVE, $site->phase());
        $this->get($site->url())->assertOk()->assertDontSee('Coming soon');
    }

    public function test_entries_are_refused_before_go_live_except_a_waitlist_signup(): void
    {
        $site = Site::factory()->create();
        $site->update(['settings' => [...$site->settings, 'go_live_at' => now()->addDays(3)->toIso8601String()]]);

        // A normal entry is refused.
        $this->postJson($site->url().'/enter', ['name' => 'Jamie', 'email' => 'a@example.com'])->assertStatus(422);

        // A waitlist signup is accepted and tagged.
        $this->postJson($site->url().'/enter', ['name' => 'Waitlist', 'email' => 'b@example.com', 'waitlist' => 1])->assertOk();
        $this->assertDatabaseHas('site_entries', ['site_id' => $site->id, 'email' => 'b@example.com', 'source' => 'waitlist']);
    }

    public function test_entries_are_refused_after_the_end(): void
    {
        $site = Site::factory()->create();
        $site->update(['settings' => [...$site->settings, 'ends_at' => now()->subDay()->toIso8601String()]]);

        $this->postJson($site->url().'/enter', ['name' => 'Jamie', 'email' => 'a@example.com'])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'This promotion has ended.']);
    }
}
