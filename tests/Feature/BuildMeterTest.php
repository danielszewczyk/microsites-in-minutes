<?php

namespace Tests\Feature;

use App\Models\BuildEvent;
use App\Models\Owner;
use App\Sites\BuildMeter;
use App\Sites\SiteComposer;
use App\Sites\UsageReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuildMeterTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_first_minute_of_build_time_is_free(): void
    {
        $owner = Owner::factory()->make(['plan' => 'free', 'build_ms_total' => 0]);

        // A fast build well within the free minute costs nothing.
        $this->assertSame(0, BuildMeter::costCents($owner, 2_000));
    }

    public function test_build_time_beyond_the_free_minute_is_billed(): void
    {
        $owner = Owner::factory()->make(['plan' => 'free', 'build_ms_total' => 60_000]);

        // One whole minute beyond the free allowance at $0.50/min.
        $this->assertSame(50, BuildMeter::costCents($owner, 60_000));
    }

    public function test_only_the_portion_past_the_free_minute_is_billed(): void
    {
        $owner = Owner::factory()->make(['plan' => 'free', 'build_ms_total' => 59_000]);

        // 1s is free, 59s is billable → ceil(59/60 * 50) = 50c.
        $this->assertSame(50, BuildMeter::costCents($owner, 60_000));
    }

    public function test_publishing_records_a_build_event_and_accrues_balance(): void
    {
        $result = app(SiteComposer::class)->publish([
            'owner_email' => 'meter@example.com',
            'business_name' => 'Meter Co',
            'prompt' => 'A quick test of the build meter and the balance counter on this account.',
            'type' => 'standard',
        ]);

        $owner = Owner::query()->where('email', 'meter@example.com')->firstOrFail();

        $this->assertDatabaseHas('build_events', ['owner_id' => $owner->id, 'kind' => BuildEvent::KIND_CREATE]);
        $this->assertGreaterThanOrEqual(0, $result['build']['ms']);
        $this->assertNotNull($result['site']->last_build_ms);
        // A single fast build stays within the free minute.
        $this->assertSame(0, (int) $owner->build_balance_cents);
    }

    public function test_usage_report_covers_build_traffic_and_storage(): void
    {
        $owner = Owner::factory()->create(['plan' => 'free']);

        $report = UsageReport::for($owner);

        foreach (['build', 'traffic', 'storage'] as $dimension) {
            $this->assertArrayHasKey($dimension, $report);
            $this->assertArrayHasKey('pct', $report[$dimension]);
        }
        $this->assertSame(60_000, $report['build']['free_ms']);
        $this->assertSame(2_000, $report['traffic']['allowance']);
    }
}
