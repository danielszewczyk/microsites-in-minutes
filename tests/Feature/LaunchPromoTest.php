<?php

namespace Tests\Feature;

use App\Models\Owner;
use App\Sites\BuildMeter;
use App\Sites\Plans;
use App\Sites\SiteComposer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaunchPromoTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_launch_promotion_makes_every_build_free(): void
    {
        config(['plans.launch_free' => true]);

        // Even a long build, well past the free minute, costs nothing.
        $owner = Owner::factory()->make(['plan' => 'free', 'build_ms_total' => 600_000]);
        $this->assertSame(0, BuildMeter::costCents($owner, 600_000));
    }

    public function test_metering_resumes_when_the_promotion_is_off(): void
    {
        config(['plans.launch_free' => false]);

        $owner = Owner::factory()->make(['plan' => 'free', 'build_ms_total' => 60_000]);
        $this->assertSame(50, BuildMeter::costCents($owner, 60_000));
    }

    public function test_publishing_under_the_promotion_accrues_no_balance(): void
    {
        config(['plans.launch_free' => true]);

        $owner = Owner::factory()->create();
        app(SiteComposer::class)->publish([
            'business_name' => 'Free Co',
            'prompt' => 'A quick launch-promo build that should accrue no balance whatsoever on this account.',
            'type' => 'standard',
        ], $owner);

        $this->assertSame(0, (int) $owner->fresh()->build_balance_cents);
    }

    public function test_the_promotion_lifts_the_single_site_cap(): void
    {
        $owner = Owner::factory()->create(['plan' => 'free']);
        $owner->sites()->create(['slug' => 'a-'.uniqid(), 'type' => 'standard', 'status' => 'published', 'inputs' => [], 'content' => [], 'settings' => [], 'manage_token_hash' => hash('sha256', 'x')]);

        config(['plans.launch_free' => false]);
        $this->assertFalse(Plans::canPublishAnother($owner), 'Free tier caps at one site when the promo is off.');

        config(['plans.launch_free' => true]);
        $this->assertTrue(Plans::canPublishAnother($owner), 'The promo lifts the cap so a second site is allowed.');
    }

    public function test_the_landing_page_shows_the_promo_banner_only_when_on(): void
    {
        config(['plans.launch_free' => true]);
        $this->get(route('home'))->assertOk()->assertSee('Launch promotion', false);

        config(['plans.launch_free' => false]);
        $this->get(route('home'))->assertOk()->assertDontSee('Launch promotion', false);
    }
}
