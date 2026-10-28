<?php

namespace Tests\Feature;

use App\Jobs\RecordSiteHit;
use App\Models\Site;
use App\Models\SiteHourlyStat;
use App\Models\SiteMinuteStat;
use App\Models\SiteStat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AnalyticsTiersTest extends TestCase
{
    use RefreshDatabase;

    public function test_recording_a_hit_fills_the_minute_hour_and_day_tiers(): void
    {
        $site = Site::factory()->create();
        $at = Carbon::parse('2026-10-28 14:37:05');

        RecordSiteHit::record($site->id, 'views', $at);

        $daily = SiteStat::query()->where('site_id', $site->id)->sole();
        $this->assertSame('2026-10-28', $daily->stat_date->toDateString());
        $this->assertSame(1, $daily->views);
        $this->assertDatabaseHas('site_hourly_stats', ['site_id' => $site->id, 'stat_hour' => '2026-10-28 14:00:00', 'views' => 1]);
        $this->assertDatabaseHas('site_minute_stats', ['site_id' => $site->id, 'stat_minute' => '2026-10-28 14:37:00', 'views' => 1]);

        // A second hit in the same minute increments the one bucket, not a duplicate.
        RecordSiteHit::record($site->id, 'views', $at->copy()->addSeconds(20));

        $this->assertDatabaseHas('site_minute_stats', ['site_id' => $site->id, 'stat_minute' => '2026-10-28 14:37:00', 'views' => 2]);
        $this->assertSame(1, SiteMinuteStat::query()->where('site_id', $site->id)->count());
    }

    public function test_entries_roll_up_into_every_tier_too(): void
    {
        $site = Site::factory()->create();

        RecordSiteHit::record($site->id, 'entries', Carbon::parse('2026-10-28 09:02:40'));

        $daily = SiteStat::query()->where('site_id', $site->id)->sole();
        $this->assertSame('2026-10-28', $daily->stat_date->toDateString());
        $this->assertSame(1, $daily->entries);
        $this->assertSame(0, $daily->views);
        $this->assertDatabaseHas('site_hourly_stats', ['site_id' => $site->id, 'stat_hour' => '2026-10-28 09:00:00', 'entries' => 1]);
        $this->assertDatabaseHas('site_minute_stats', ['site_id' => $site->id, 'stat_minute' => '2026-10-28 09:02:00', 'entries' => 1]);
    }

    public function test_the_stats_endpoint_serves_each_range_at_the_right_resolution(): void
    {
        $site = Site::factory()->withToken('tok')->create();

        SiteMinuteStat::query()->create(['site_id' => $site->id, 'stat_minute' => now()->startOfMinute(), 'views' => 5, 'entries' => 1]);
        SiteHourlyStat::query()->create(['site_id' => $site->id, 'stat_hour' => now()->startOfHour(), 'views' => 9, 'entries' => 2]);
        SiteStat::query()->create(['site_id' => $site->id, 'stat_date' => now()->toDateString(), 'views' => 20, 'entries' => 4]);

        $cases = ['hour' => 60, 'day' => 24, 'week' => 7, 'month' => 30, 'year' => 12];

        foreach ($cases as $range => $buckets) {
            $res = $this->withCookie($site->cookieName(), 'tok')
                ->get(route('microsite.stats', $site->slug).'?range='.$range, ['Accept' => 'application/json'])
                ->assertOk()
                ->assertJson(['range' => $range]);

            $this->assertCount($buckets, $res->json('series'), "range {$range} bucket count");
        }

        // The seeded minute bucket is the latest point of the hour range.
        $hour = $this->withCookie($site->cookieName(), 'tok')
            ->get(route('microsite.stats', $site->slug).'?range=hour', ['Accept' => 'application/json'])->json('series');
        $this->assertSame(5, collect($hour)->sum('views'));

        // An unknown range falls back to the 30-day month view.
        $this->withCookie($site->cookieName(), 'tok')
            ->get(route('microsite.stats', $site->slug).'?range=bogus', ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJson(['range' => 'month']);
    }

    public function test_prune_enforces_the_retention_windows(): void
    {
        $site = Site::factory()->create();

        SiteMinuteStat::query()->create(['site_id' => $site->id, 'stat_minute' => now()->subDays(3), 'views' => 1]);
        SiteMinuteStat::query()->create(['site_id' => $site->id, 'stat_minute' => now()->subMinutes(5), 'views' => 1]);
        SiteHourlyStat::query()->create(['site_id' => $site->id, 'stat_hour' => now()->subDays(100)->startOfHour(), 'views' => 1]);
        SiteHourlyStat::query()->create(['site_id' => $site->id, 'stat_hour' => now()->subDays(10)->startOfHour(), 'views' => 1]);

        $this->artisan('stats:prune')->assertSuccessful();

        $this->assertSame(1, SiteMinuteStat::query()->where('site_id', $site->id)->count());
        $this->assertSame(1, SiteHourlyStat::query()->where('site_id', $site->id)->count());
    }

    public function test_the_chart_shows_the_range_toggles(): void
    {
        $site = Site::factory()->withToken('tok')->create();

        $this->withCookie($site->cookieName(), 'tok')
            ->get(route('microsite.manage', $site->slug))
            ->assertOk()
            ->assertSee('id="stat-ranges"', false)
            ->assertSee('data-range="hour"', false)
            ->assertSee('data-range="year"', false)
            ->assertSee('Views per day, last 30 days');
    }
}
