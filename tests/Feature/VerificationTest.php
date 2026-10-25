<?php

namespace Tests\Feature;

use App\Leads\EmailVerifier;
use App\Leads\PhoneVerifier;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_entry_captures_ip_user_agent_and_country(): void
    {
        $site = Site::factory()->create();

        $this->withHeaders(['CF-IPCountry' => 'US', 'User-Agent' => 'TestAgent/1.0'])
            ->postJson($site->url().'/enter', ['name' => 'Jamie', 'email' => 'jamie@example.com'])
            ->assertOk();

        $entry = $site->entries()->firstOrFail();
        $this->assertSame('US', $entry->country);
        $this->assertNotNull($entry->ip);
        $this->assertStringContainsString('TestAgent', (string) $entry->user_agent);
    }

    public function test_email_is_verified_when_the_add_on_is_on(): void
    {
        $this->app->bind(EmailVerifier::class, fn () => new class implements EmailVerifier
        {
            public function verify(string $email): array
            {
                return ['status' => self::DELIVERABLE, 'score' => 95];
            }
        });

        $site = Site::factory()->create();
        $site->update(['settings' => [...$site->settings, 'verify_emails' => true]]);

        $this->postJson($site->url().'/enter', ['name' => 'Jamie', 'email' => 'jamie@example.com'])->assertOk();

        $entry = $site->entries()->firstOrFail();
        $this->assertSame('deliverable', $entry->email_status);
        $this->assertSame(95, $entry->email_score);
    }

    public function test_phone_is_verified_when_the_add_on_is_on(): void
    {
        $this->app->bind(PhoneVerifier::class, fn () => new class implements PhoneVerifier
        {
            public function verify(string $phone): array
            {
                return ['status' => self::VALID, 'line' => 'mobile'];
            }
        });

        $site = Site::factory()->create();
        $site->update(['settings' => [...$site->settings, 'verify_phones' => true]]);

        $this->postJson($site->url().'/enter', ['name' => 'Jamie', 'phone' => '+15551234567'])->assertOk();

        $entry = $site->entries()->firstOrFail();
        $this->assertSame('valid', $entry->phone_status);
        $this->assertSame('mobile', $entry->phone_line);
    }

    public function test_no_verification_when_the_add_on_is_off(): void
    {
        $site = Site::factory()->create();

        $this->postJson($site->url().'/enter', ['name' => 'Jamie', 'email' => 'jamie@example.com'])->assertOk();

        $this->assertNull($site->entries()->first()->email_status);
    }

    public function test_csv_includes_verification_and_detail_columns(): void
    {
        $site = Site::factory()->withToken('tok')->create();
        $this->withHeaders(['CF-IPCountry' => 'CA'])->postJson($site->url().'/enter', ['name' => 'Jamie', 'email' => 'j@example.com']);

        $csv = $this->withCookie($site->cookieName(), 'tok')
            ->get(route('microsite.entries.export', $site->slug))->streamedContent();

        foreach (['Email status', 'Phone status', 'Country', 'IP', 'User agent'] as $header) {
            $this->assertStringContainsString($header, $csv);
        }
        $this->assertStringContainsString('CA', $csv);
    }
}
