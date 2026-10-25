<?php

namespace Tests\Feature;

use App\Models\Owner;
use App\Sites\BuildMeter;
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

    public function test_the_landing_page_shows_the_promo_banner_only_when_on(): void
    {
        config(['plans.launch_free' => true]);
        $this->get(route('home'))->assertOk()->assertSee('Launch promotion', false);

        config(['plans.launch_free' => false]);
        $this->get(route('home'))->assertOk()->assertDontSee('Launch promotion', false);
    }
}
