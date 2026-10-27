<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Models\SiteEntry;
use App\Support\EntryCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The email code verification add-on: an email entry must carry a valid
 * one-time code (and a Turnstile token when configured) before it is accepted.
 */
class EmailCodeFlowTest extends TestCase
{
    use RefreshDatabase;

    private function codeSite(): Site
    {
        $site = Site::factory()->create();
        $site->update(['settings' => array_merge($site->settings ?? [], ['require_email_code' => true])]);

        return $site->fresh();
    }

    private function enableTurnstile(bool $pass): void
    {
        config(['services.turnstile.site_key' => 'test-site', 'services.turnstile.secret' => 'test-secret']);
        // The fake echoes the app's own host so the server-side hostname check
        // (which compares against the request host) passes on a successful verdict.
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => $pass, 'hostname' => $host])]);
    }

    public function test_an_email_entry_without_a_code_is_rejected(): void
    {
        $site = $this->codeSite();

        $this->postJson($site->url().'/enter', ['name' => 'Jamie', 'email' => 'jamie@example.com'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');

        $this->assertDatabaseCount('site_entries', 0);
    }

    public function test_a_valid_code_lets_the_entry_through_and_burns_it(): void
    {
        $site = $this->codeSite();
        $code = EntryCode::issue($site->id, 'jamie@example.com');

        $this->postJson($site->url().'/enter', ['name' => 'Jamie', 'email' => 'jamie@example.com', 'code' => $code])
            ->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('site_entries', ['site_id' => $site->id, 'email' => 'jamie@example.com']);
        $this->assertFalse(EntryCode::check($site->id, 'jamie@example.com', $code));
    }

    public function test_a_phone_only_entry_cannot_bypass_the_gate(): void
    {
        $site = $this->codeSite();

        // A bot omitting the email must not slip past the code/Turnstile gate.
        $this->postJson($site->url().'/enter', ['name' => 'Bot', 'phone' => '5551234567'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('site_entries', 0);
    }

    public function test_a_known_email_cannot_be_probed_without_a_code(): void
    {
        $site = $this->codeSite();
        SiteEntry::factory()->for($site)->create(['email' => 'jamie@example.com']);

        // Without a code, a request for an already-entered email must be stopped
        // by the gate, not answered with "you're already entered".
        $this->postJson($site->url().'/enter', ['name' => 'Jamie', 'email' => 'jamie@example.com'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');
    }

    public function test_array_inputs_do_not_crash_the_endpoints(): void
    {
        $site = $this->codeSite();

        $this->postJson($site->url().'/enter', [
            'name' => 'Jamie', 'email' => 'jamie@example.com', 'code' => ['x'], 'cf-turnstile-response' => ['y'],
        ])->assertStatus(422);

        $this->postJson($site->url().'/send-code', [
            'email' => 'jamie@example.com', 'email_confirm' => 'jamie@example.com', 'cf-turnstile-response' => ['y'],
        ])->assertOk();
    }

    public function test_confirm_email_match_is_case_insensitive(): void
    {
        $site = $this->codeSite();

        $this->postJson($site->url().'/send-code', ['email' => 'Jamie@Example.com', 'email_confirm' => 'jamie@example.com'])
            ->assertOk()->assertJson(['ok' => true]);

        // The code is keyed to the lower-cased address, matching the entry step.
        $this->assertTrue(EntryCode::pending($site->id, 'jamie@example.com'));
    }

    public function test_a_waitlist_signup_is_exempt_from_the_code_gate(): void
    {
        $site = Site::factory()->create();
        $site->update(['settings' => array_merge($site->settings ?? [], [
            'require_email_code' => true,
            'go_live_at' => now()->addDays(3)->toIso8601String(),
        ])]);

        $this->postJson($site->url().'/enter', ['name' => 'Jamie', 'email' => 'jamie@example.com', 'waitlist' => 1])
            ->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('site_entries', ['site_id' => $site->id, 'email' => 'jamie@example.com', 'source' => 'waitlist']);
    }

    public function test_the_embed_points_to_the_full_page_when_the_addon_is_on(): void
    {
        $site = $this->codeSite();

        $this->get($site->url().'/embed')
            ->assertOk()
            ->assertSee('Enter on the full page')
            ->assertDontSee('id="f"', false);
    }

    public function test_turnstile_network_failure_fails_closed(): void
    {
        config(['services.turnstile.site_key' => 'test-site', 'services.turnstile.secret' => 'test-secret']);
        Http::fake(fn () => throw new ConnectionException('Cloudflare unreachable'));
        $site = $this->codeSite();

        $this->postJson($site->url().'/send-code', [
            'email' => 'jamie@example.com', 'email_confirm' => 'jamie@example.com', 'cf-turnstile-response' => 'token',
        ])->assertStatus(422);

        $this->assertFalse(EntryCode::pending($site->id, 'jamie@example.com'));
    }

    public function test_a_wrong_code_is_rejected(): void
    {
        $site = $this->codeSite();
        EntryCode::issue($site->id, 'jamie@example.com');

        $this->postJson($site->url().'/enter', ['name' => 'Jamie', 'email' => 'jamie@example.com', 'code' => '000000'])
            ->assertStatus(422)->assertJsonValidationErrors('code');

        $this->assertDatabaseCount('site_entries', 0);
    }

    public function test_send_code_requires_matching_confirmation(): void
    {
        $site = $this->codeSite();

        $this->postJson($site->url().'/send-code', ['email' => 'jamie@example.com', 'email_confirm' => 'typo@example.com'])
            ->assertStatus(422)->assertJsonValidationErrors('email_confirm');

        $this->assertFalse(EntryCode::pending($site->id, 'jamie@example.com'));
    }

    public function test_send_code_issues_a_pending_code(): void
    {
        $site = $this->codeSite();

        $this->postJson($site->url().'/send-code', ['email' => 'jamie@example.com', 'email_confirm' => 'jamie@example.com'])
            ->assertOk()->assertJson(['ok' => true]);

        $this->assertTrue(EntryCode::pending($site->id, 'jamie@example.com'));
    }

    public function test_send_code_rejects_disposable_domains(): void
    {
        $site = $this->codeSite();

        $this->postJson($site->url().'/send-code', ['email' => 'bot@mailinator.com', 'email_confirm' => 'bot@mailinator.com'])
            ->assertStatus(422)->assertJsonValidationErrors('email');

        $this->assertFalse(EntryCode::pending($site->id, 'bot@mailinator.com'));
    }

    public function test_send_code_does_not_resend_to_an_already_entered_email(): void
    {
        $site = $this->codeSite();
        SiteEntry::factory()->for($site)->create(['email' => 'jamie@example.com']);

        // No code is issued for an already-entered address...
        $this->postJson($site->url().'/send-code', ['email' => 'jamie@example.com', 'email_confirm' => 'jamie@example.com'])
            ->assertOk()->assertJson(['ok' => true, 'retry_after' => EntryCode::RESEND_SECONDS])
            ->assertJsonMissing(['already' => true]);

        $this->assertFalse(EntryCode::pending($site->id, 'jamie@example.com'));
    }

    public function test_send_code_response_does_not_reveal_whether_an_email_has_entered(): void
    {
        $site = $this->codeSite();
        SiteEntry::factory()->for($site)->create(['email' => 'entered@example.com']);

        // The already-entered reply and the new-email reply are identical, so
        // the response can't be used to enumerate who has entered.
        $entered = $this->postJson($site->url().'/send-code', ['email' => 'entered@example.com', 'email_confirm' => 'entered@example.com'])
            ->assertOk()->json();
        $fresh = $this->postJson($site->url().'/send-code', ['email' => 'new@example.com', 'email_confirm' => 'new@example.com'])
            ->assertOk()->json();

        $this->assertSame($entered, $fresh);
    }

    public function test_the_waitlist_is_bot_gated_by_turnstile_when_configured(): void
    {
        $this->enableTurnstile(pass: false);
        $site = Site::factory()->create();
        $site->update(['settings' => array_merge($site->settings ?? [], [
            'require_email_code' => true,
            'go_live_at' => now()->addDays(3)->toIso8601String(),
        ])]);

        // A failing Turnstile now blocks even a waitlist signup — no ungated path.
        $this->postJson($site->url().'/enter', [
            'name' => 'Waitlist', 'email' => 'jamie@example.com', 'waitlist' => 1, 'cf-turnstile-response' => 'bad',
        ])->assertStatus(422);

        $this->assertDatabaseCount('site_entries', 0);
    }

    public function test_send_code_is_404_when_the_addon_is_off(): void
    {
        $site = Site::factory()->create();

        $this->postJson($site->url().'/send-code', ['email' => 'a@example.com', 'email_confirm' => 'a@example.com'])
            ->assertNotFound();
    }

    public function test_a_filled_honeypot_sends_no_code(): void
    {
        $site = $this->codeSite();

        $this->postJson($site->url().'/send-code', [
            'email' => 'jamie@example.com',
            'email_confirm' => 'jamie@example.com',
            'website' => 'http://spam.example',
        ])->assertOk();

        $this->assertFalse(EntryCode::pending($site->id, 'jamie@example.com'));
    }

    public function test_send_code_rate_limits_per_email_and_ip(): void
    {
        $site = $this->codeSite();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson($site->url().'/send-code', ['email' => 'jamie@example.com', 'email_confirm' => 'jamie@example.com'])
                ->assertOk();
            $this->travel(EntryCode::RESEND_SECONDS + 1)->seconds();
        }

        $this->postJson($site->url().'/send-code', ['email' => 'jamie@example.com', 'email_confirm' => 'jamie@example.com'])
            ->assertStatus(429);
    }

    public function test_turnstile_blocks_send_code_when_the_token_is_invalid(): void
    {
        $this->enableTurnstile(pass: false);
        $site = $this->codeSite();

        $this->postJson($site->url().'/send-code', [
            'email' => 'jamie@example.com', 'email_confirm' => 'jamie@example.com', 'cf-turnstile-response' => 'bad',
        ])->assertStatus(422);

        $this->assertFalse(EntryCode::pending($site->id, 'jamie@example.com'));
    }

    public function test_turnstile_blocks_the_entry_endpoint_when_invalid(): void
    {
        $this->enableTurnstile(pass: false);
        $site = $this->codeSite();
        $code = EntryCode::issue($site->id, 'jamie@example.com');

        $this->postJson($site->url().'/enter', [
            'name' => 'Jamie', 'email' => 'jamie@example.com', 'code' => $code, 'cf-turnstile-response' => 'bad',
        ])->assertStatus(422);

        $this->assertDatabaseCount('site_entries', 0);
    }

    public function test_verification_sender_defaults_to_the_platform_and_is_overridable(): void
    {
        config(['services.verification.from_address' => 'verify@micrositesinminutes.com']);
        $site = Site::factory()->create();

        // Defaults to the platform's own verified domain.
        $this->assertSame('verify@micrositesinminutes.com', $site->verificationSender()['address']);

        // A per-site override (the custom-domain seam) is honored without any
        // call-site change.
        $site->update(['settings' => array_merge($site->settings ?? [], [
            'verification_from' => ['address' => 'noreply@acme.test', 'name' => 'Acme'],
        ])]);

        $sender = $site->fresh()->verificationSender();
        $this->assertSame('noreply@acme.test', $sender['address']);
        $this->assertSame('Acme', $sender['name']);
    }

    public function test_the_entry_page_shows_the_confirm_email_and_code_fields(): void
    {
        $site = $this->codeSite();

        $this->get($site->url())
            ->assertOk()
            ->assertSee('Confirm email')
            ->assertSee('name="email_confirm"', false)
            ->assertSee('class="code-boxes"', false)
            ->assertSee('Resend code');
    }

    public function test_the_turnstile_widget_loads_only_when_keys_are_set(): void
    {
        $this->get($this->codeSite()->url())
            ->assertOk()
            ->assertDontSee('challenges.cloudflare.com', false);

        config(['services.turnstile.site_key' => 'test-site-key', 'services.turnstile.secret' => 'test-secret']);

        // A different site renders fresh HTML (the page cache is keyed per site).
        $this->get($this->codeSite()->url())
            ->assertOk()
            ->assertSee('challenges.cloudflare.com', false)
            ->assertSee('test-site-key', false);
    }

    public function test_a_normal_site_has_no_code_fields(): void
    {
        $site = Site::factory()->create();

        $this->get($site->url())
            ->assertOk()
            ->assertDontSee('name="email_confirm"', false)
            ->assertDontSee('class="code-boxes"', false);
    }

    public function test_turnstile_rejects_a_token_solved_on_a_different_host(): void
    {
        config(['services.turnstile.site_key' => 'test-site', 'services.turnstile.secret' => 'test-secret']);
        // Verdict is success, but the challenge was solved on another host — a
        // replayed/cross-tenant token must be rejected.
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true, 'hostname' => 'evil.example'])]);
        $site = $this->codeSite();
        $code = EntryCode::issue($site->id, 'jamie@example.com');

        $this->postJson($site->url().'/enter', [
            'name' => 'Jamie', 'email' => 'jamie@example.com', 'code' => $code, 'cf-turnstile-response' => 'good',
        ])->assertStatus(422);

        $this->assertDatabaseCount('site_entries', 0);
    }

    public function test_turnstile_pass_allows_the_entry(): void
    {
        $this->enableTurnstile(pass: true);
        $site = $this->codeSite();
        $code = EntryCode::issue($site->id, 'jamie@example.com');

        $this->postJson($site->url().'/enter', [
            'name' => 'Jamie', 'email' => 'jamie@example.com', 'code' => $code, 'cf-turnstile-response' => 'good',
        ])->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('site_entries', ['site_id' => $site->id, 'email' => 'jamie@example.com']);
    }
}
