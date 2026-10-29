<?php

namespace Tests\Feature;

use App\Models\Owner;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebmasterTest extends TestCase
{
    use RefreshDatabase;

    private function allowlist(string ...$emails): void
    {
        config(['webmaster.emails' => array_map('strtolower', $emails)]);
    }

    public function test_a_guest_is_sent_to_login(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
    }

    public function test_a_signed_in_non_webmaster_gets_a_404(): void
    {
        $this->allowlist('boss@example.com');
        $owner = Owner::factory()->create(['email' => 'someone@example.com']);

        $this->actingAs($owner)->get('/admin')->assertNotFound();
    }

    public function test_a_webmaster_sees_every_site_with_its_live_link(): void
    {
        $this->allowlist('boss@example.com');
        $boss = Owner::factory()->create(['email' => 'boss@example.com']);

        $mine = Site::factory()->create([
            'inputs' => ['business_name' => 'Acme Dental'],
            'slug' => 'acme-dental-abc123',
        ]);
        $theirs = Site::factory()->for(Owner::factory()->create(['email' => 'other@example.com']))->create([
            'inputs' => ['business_name' => 'Zed Bakery'],
            'slug' => 'zed-bakery-xyz789',
        ]);

        $response = $this->actingAs($boss)->get('/admin');

        $response->assertOk();
        // Both owners' sites appear, each linked to its public address.
        $response->assertSee('Acme Dental');
        $response->assertSee($mine->url(), false);
        $response->assertSee('Zed Bakery');
        $response->assertSee($theirs->url(), false);
        $response->assertSee('other@example.com');
    }

    public function test_the_allowlist_match_is_case_insensitive(): void
    {
        $this->allowlist('boss@example.com');
        $boss = Owner::factory()->create(['email' => 'BOSS@Example.com']);

        $this->actingAs($boss)->get('/admin')->assertOk();
    }

    public function test_an_empty_allowlist_closes_the_area_to_everyone(): void
    {
        $this->allowlist();
        $owner = Owner::factory()->create(['email' => 'boss@example.com']);

        $this->actingAs($owner)->get('/admin')->assertNotFound();
    }

    public function test_an_unverified_allowlisted_account_is_refused(): void
    {
        // Registration signs an owner in before they verify, so an allowlisted
        // address that hasn't been verified must not reach the admin area.
        $this->allowlist('boss@example.com');
        $unverified = Owner::factory()->unverified()->create(['email' => 'boss@example.com']);

        $this->actingAs($unverified)->get('/admin')->assertNotFound();
    }
}
