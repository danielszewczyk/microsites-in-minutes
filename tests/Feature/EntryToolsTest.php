<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Models\SiteAlarm;
use App\Sites\SiteComposer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EntryToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_embed_page_renders_a_frameable_form(): void
    {
        $site = Site::factory()->create();

        $this->get(route('microsite.embed', $site->slug))
            ->assertOk()
            ->assertHeader('Content-Security-Policy', 'frame-ancestors *')
            ->assertSee('name="name"', false);
    }

    public function test_entry_is_forwarded_to_the_webhook(): void
    {
        Http::fake();
        $site = Site::factory()->create();
        // A public IP literal so the SSRF guard allows it without live DNS.
        $site->update(['settings' => [...$site->settings, 'webhook_url' => 'https://1.1.1.1/hook']]);

        $this->postJson($site->url().'/enter', ['name' => 'Jamie', 'email' => 'jamie@example.com'])->assertOk();

        Http::assertSent(fn ($request) => $request->url() === 'https://1.1.1.1/hook' && $request['name'] === 'Jamie');
    }

    public function test_webhook_to_a_private_or_loopback_host_is_blocked(): void
    {
        Http::fake();
        $site = Site::factory()->create();

        foreach (['http://127.0.0.1/x', 'http://[::1]/x', 'http://169.254.169.254/latest', 'http://10.0.0.5/x', 'http://[::ffff:127.0.0.1]/x'] as $bad) {
            $site->update(['settings' => [...$site->settings, 'webhook_url' => $bad]]);
            $this->postJson($site->url().'/enter', ['name' => 'Jamie', 'email' => 'a'.md5($bad).'@example.com'])->assertOk();
        }

        Http::assertNothingSent();
    }

    public function test_confirmation_holds_the_entry_until_the_link_is_clicked(): void
    {
        $site = Site::factory()->create();
        $site->update(['settings' => [...$site->settings, 'require_confirmation' => true]]);

        $this->postJson($site->url().'/enter', ['name' => 'Jamie', 'email' => 'jamie@example.com'])
            ->assertOk()
            ->assertSee('confirm', false);

        $entry = $site->entries()->firstOrFail();
        $this->assertNull($entry->confirmed_at);
        $this->assertNotNull($entry->confirm_token);

        $this->get(route('microsite.confirm', [$site->slug, $entry->confirm_token]))
            ->assertOk()
            ->assertSee('confirmed', false);

        $this->assertNotNull($entry->fresh()->confirmed_at);
    }

    public function test_import_api_needs_the_token_and_creates_entries(): void
    {
        $site = Site::factory()->withToken('apitoken')->create();

        $this->postJson(route('api.entries.store', $site->slug), ['name' => 'Jane'])->assertStatus(401);

        $this->withToken('apitoken')
            ->postJson(route('api.entries.store', $site->slug), [
                'entries' => [['name' => 'Jane', 'email' => 'jane@example.com'], ['name' => 'Jim']],
            ])
            ->assertStatus(201)
            ->assertJson(['created' => 2]);

        $this->assertDatabaseCount('site_entries', 2);
    }

    public function test_new_sites_get_a_default_views_alarm(): void
    {
        $site = app(SiteComposer::class)->publish([
            'owner_email' => 'pat@example.com',
            'business_name' => 'Paul\'s Parlor',
            'prompt' => 'A salon that wants more weekday bookings this autumn season starting now.',
            'type' => 'standard',
        ])['site'];

        $this->assertDatabaseHas('site_alarms', ['site_id' => $site->id, 'metric' => 'views', 'threshold' => 100, 'enabled' => true]);
    }

    public function test_an_alarm_fires_once_when_the_threshold_is_crossed(): void
    {
        Mail::fake();
        $site = Site::factory()->create(['view_count' => 4]);
        $site->alarms()->create(['metric' => SiteAlarm::VIEWS, 'threshold' => 5, 'email' => 'owner@example.com', 'enabled' => true]);

        // Fifth view crosses the threshold.
        $this->get($site->url())->assertOk();

        $this->assertNotNull($site->alarms()->first()->triggered_at);

        // A sixth view does not fire again.
        $firedAt = $site->alarms()->first()->triggered_at;
        $this->get($site->url())->assertOk();
        $this->assertEquals($firedAt, $site->alarms()->first()->triggered_at);
    }
}
