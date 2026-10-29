<?php

namespace Tests\Feature;

use App\Console\Commands\PruneStats;
use App\Jobs\RecordSiteClick;
use App\Models\Owner;
use App\Models\Site;
use App\Models\SiteClickEvent;
use App\Models\SiteClickStat;
use App\Sites\ClickTargets;
use App\Sites\Plans;
use App\Sites\SiteComposer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ClickTrackingTest extends TestCase
{
    use RefreshDatabase;

    /** A plausible desktop browser user agent, so the ingest bot filter lets it through. */
    private const string BROWSER_UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0 Safari/537.36';

    /**
     * Publish a site through the real composer (so it carries the publish-time
     * track ids), owned by the given owner.
     *
     * @param  array<string, mixed>  $overrides
     * @return array{site: Site, token: string}
     */
    private function publish(?Owner $owner = null, array $overrides = []): array
    {
        $owner ??= Owner::factory()->pro()->create();

        $result = app(SiteComposer::class)->publish([
            'business_name' => 'Paul\'s Parlor',
            'prompt' => 'A friendly neighbourhood hair salon that wants more weekday bookings.',
            'type' => 'standard',
            ...$overrides,
        ], $owner);

        return ['site' => $result['site'], 'token' => $result['token']];
    }

    public function test_publishing_assigns_a_stable_track_id_to_every_slot(): void
    {
        $site = $this->publish()['site'];

        $map = $site->trackIds();

        $this->assertSame(array_keys(ClickTargets::SLOTS), array_keys($map));

        foreach ($map as $id) {
            $this->assertIsString($id);
            $this->assertNotSame('', $id);
        }

        // The ids are distinct — one element can't be confused for another.
        $this->assertCount(count($map), array_unique($map));
    }

    public function test_regenerating_preserves_the_track_ids(): void
    {
        ['site' => $site] = $this->publish();
        $before = $site->trackIds();

        app(SiteComposer::class)->regenerate($site, [
            'business_name' => 'Paul\'s Parlor',
            'prompt' => 'A totally rewritten prompt with a different call to action entirely.',
            'type' => 'standard',
            'cta_label' => 'Book now instead',
        ]);

        $this->assertSame($before, $site->fresh()->trackIds());
    }

    public function test_a_pro_page_carries_track_ids_and_the_beacon(): void
    {
        $site = $this->publish()['site'];
        $heroId = $site->trackIds()['hero_cta'];

        $this->get($site->url())
            ->assertOk()
            ->assertSee('data-mim-id="'.$heroId.'"', false)
            ->assertSee('navigator.sendBeacon(data.track', false)
            ->assertSee('"track":"', false);
    }

    public function test_a_free_page_has_no_click_tracking(): void
    {
        $site = $this->publish(Owner::factory()->create())['site'];

        $this->get($site->url())
            ->assertOk()
            ->assertDontSee('data-mim-id', false)
            ->assertSee('"track":null', false);
    }

    public function test_ingest_counts_a_click_on_a_known_element_and_rolls_it_up(): void
    {
        $site = $this->publish()['site'];
        $heroId = $site->trackIds()['hero_cta'];

        $this->post(route('microsite.track', $site->slug), ['id' => $heroId], ['User-Agent' => self::BROWSER_UA])
            ->assertNoContent();

        // A raw firehose row (anonymous: no ip/ua/visitor columns exist).
        $this->assertDatabaseHas('site_click_events', [
            'site_id' => $site->id,
            'element_id' => $heroId,
            'href' => '#enter',
        ]);

        // And the durable daily roll-up, keyed site × element × day.
        $stat = SiteClickStat::query()->where('site_id', $site->id)->where('element_id', $heroId)->sole();
        $this->assertSame(now()->toDateString(), $stat->stat_date->toDateString());
        $this->assertSame(1, (int) $stat->clicks);
    }

    public function test_a_second_click_increments_the_same_day_row(): void
    {
        $site = $this->publish()['site'];
        $heroId = $site->trackIds()['hero_cta'];
        $at = Carbon::parse('2026-10-29 10:15:00');

        RecordSiteClick::record($site->id, $heroId, '#enter', $at);
        RecordSiteClick::record($site->id, $heroId, '#enter', $at->copy()->addMinutes(3));

        $this->assertSame(1, SiteClickStat::query()->where('site_id', $site->id)->where('element_id', $heroId)->count());
        $this->assertSame(2, (int) SiteClickStat::query()->where('site_id', $site->id)->where('element_id', $heroId)->sole()->clicks);
        $this->assertSame(2, SiteClickEvent::query()->where('site_id', $site->id)->count());
    }

    public function test_ingest_drops_an_unknown_element_id(): void
    {
        $site = $this->publish()['site'];

        $this->post(route('microsite.track', $site->slug), ['id' => 'not-a-real-id'], ['User-Agent' => self::BROWSER_UA])
            ->assertNoContent();

        $this->assertSame(0, SiteClickEvent::query()->where('site_id', $site->id)->count());
        $this->assertSame(0, SiteClickStat::query()->where('site_id', $site->id)->count());
    }

    public function test_ingest_drops_an_obvious_bot(): void
    {
        $site = $this->publish()['site'];
        $heroId = $site->trackIds()['hero_cta'];

        $this->post(route('microsite.track', $site->slug), ['id' => $heroId], ['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'])
            ->assertNoContent();

        $this->assertSame(0, SiteClickEvent::query()->where('site_id', $site->id)->count());
    }

    public function test_click_tracking_is_a_paid_only_plan_flag(): void
    {
        $this->assertFalse((bool) Plans::tier('free')['click_tracking']);
        $this->assertTrue((bool) Plans::tier('pro')['click_tracking']);
        $this->assertTrue((bool) Plans::tier('business')['click_tracking']);
    }

    public function test_ingest_drops_a_request_with_no_user_agent(): void
    {
        $site = $this->publish()['site'];
        $heroId = $site->trackIds()['hero_cta'];

        $this->post(route('microsite.track', $site->slug), ['id' => $heroId], ['User-Agent' => ''])
            ->assertNoContent();

        $this->assertSame(0, SiteClickEvent::query()->where('site_id', $site->id)->count());
    }

    public function test_ingest_is_rate_limited_per_ip(): void
    {
        $site = $this->publish()['site'];
        $heroId = $site->trackIds()['hero_cta'];

        // The route throttle allows 60 a minute; the 61st from the same IP is 429.
        for ($i = 0; $i < 60; $i++) {
            $this->post(route('microsite.track', $site->slug), ['id' => $heroId], ['User-Agent' => self::BROWSER_UA])
                ->assertNoContent();
        }

        $this->post(route('microsite.track', $site->slug), ['id' => $heroId], ['User-Agent' => self::BROWSER_UA])
            ->assertStatus(429);
    }

    public function test_ingest_is_dropped_for_a_site_without_the_entitlement(): void
    {
        // Published Pro (so a valid track id exists), then the plan lapses.
        $owner = Owner::factory()->pro()->create();
        $site = $this->publish($owner)['site'];
        $heroId = $site->trackIds()['hero_cta'];

        $owner->update(['plan' => 'free']);

        $this->post(route('microsite.track', $site->slug), ['id' => $heroId], ['User-Agent' => self::BROWSER_UA])
            ->assertNoContent();

        $this->assertSame(0, SiteClickEvent::query()->where('site_id', $site->id)->count());
        $this->assertSame(0, SiteClickStat::query()->where('site_id', $site->id)->count());
    }

    public function test_prune_drops_old_raw_click_events_but_keeps_the_daily_rollup(): void
    {
        $site = Site::factory()->create();

        SiteClickEvent::query()->create(['site_id' => $site->id, 'element_id' => 'abc', 'href' => '#enter', 'occurred_at' => now()->subDays(PruneStats::CLICK_EVENT_DAYS + 1)]);
        SiteClickEvent::query()->create(['site_id' => $site->id, 'element_id' => 'abc', 'href' => '#enter', 'occurred_at' => now()->subDay()]);
        SiteClickStat::query()->create(['site_id' => $site->id, 'element_id' => 'abc', 'stat_date' => now()->subDays(400)->toDateString(), 'clicks' => 9]);

        $this->artisan('stats:prune')->assertSuccessful();

        // Only the recent raw event survives; the durable roll-up is never pruned.
        $this->assertSame(1, SiteClickEvent::query()->where('site_id', $site->id)->count());
        $this->assertSame(1, SiteClickStat::query()->where('site_id', $site->id)->count());
    }

    public function test_dashboard_shows_most_clicked_for_pro_and_hides_it_for_free(): void
    {
        ['site' => $site, 'token' => $token] = $this->publish(Owner::factory()->pro()->create());
        $heroId = $site->trackIds()['hero_cta'];

        SiteClickStat::query()->create(['site_id' => $site->id, 'element_id' => $heroId, 'stat_date' => now()->toDateString(), 'clicks' => 7]);

        $this->withCookie($site->cookieName(), $token)
            ->get(route('microsite.manage', $site->slug))
            ->assertOk()
            ->assertSee('Most-clicked links &amp; buttons', false)
            ->assertSee('Hero call-to-action')
            ->assertSee('7');

        // A free site's dashboard never shows the panel.
        ['site' => $free, 'token' => $freeToken] = $this->publish(Owner::factory()->create());

        $this->withCookie($free->cookieName(), $freeToken)
            ->get(route('microsite.manage', $free->slug))
            ->assertOk()
            ->assertDontSee('Most-clicked links');
    }
}
