<?php

namespace Tests\Feature;

use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_valid_entry_is_stored_and_counted(): void
    {
        $site = Site::factory()->create();

        $this->postJson($site->url().'/enter', [
            'name' => 'Jamie Lee',
            'email' => 'jamie@example.com',
        ])->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('site_entries', ['site_id' => $site->id, 'name' => 'Jamie Lee', 'email' => 'jamie@example.com']);
        $this->assertSame(1, $site->fresh()->entry_count);
        $this->assertDatabaseHas('site_stats', ['site_id' => $site->id, 'entries' => 1]);
    }

    public function test_the_ip_is_stored_only_as_a_hash(): void
    {
        $site = Site::factory()->create();

        $this->postJson($site->url().'/enter', ['name' => 'A', 'phone' => '555-1212']);

        $entry = $site->entries()->firstOrFail();
        $this->assertNotNull($entry->ip_hash);
        $this->assertSame(64, strlen($entry->ip_hash));
    }

    public function test_a_filled_honeypot_is_accepted_but_stores_nothing(): void
    {
        $site = Site::factory()->create();

        $this->postJson($site->url().'/enter', [
            'name' => 'Spam Bot',
            'email' => 'bot@example.com',
            'website' => 'http://spam.example',
        ])->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseCount('site_entries', 0);
    }

    public function test_name_is_required(): void
    {
        $site = Site::factory()->create();

        $this->postJson($site->url().'/enter', ['email' => 'jamie@example.com'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_an_email_or_phone_is_required(): void
    {
        $site = Site::factory()->create();

        $this->postJson($site->url().'/enter', ['name' => 'Jamie'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('site_entries', 0);
    }

    public function test_the_entry_route_is_csrf_exempt(): void
    {
        $site = Site::factory()->create();

        // No CSRF token, no session: a cacheable public page relies on the
        // honeypot and rate limit instead.
        $this->post($site->url().'/enter', ['name' => 'Jamie', 'email' => 'jamie@example.com'], ['Accept' => 'application/json'])
            ->assertOk();
    }
}
