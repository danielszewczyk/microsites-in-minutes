<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Models\SiteAsset;
use App\Models\SiteEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DateOfBirthEntryTest extends TestCase
{
    use RefreshDatabase;

    private function dobSite(string $token = 'secret-token'): Site
    {
        $site = Site::factory()->withToken($token)->create();
        $site->update(['settings' => [...$site->settings, 'collect_dob' => true]]);

        return $site;
    }

    public function test_a_date_of_birth_is_stored_in_the_entry_extra_when_the_add_on_is_on(): void
    {
        $site = $this->dobSite();

        $this->post($site->url().'/enter', [
            'name' => 'Pat',
            'email' => 'pat@example.com',
            'dob' => '1990-05-04',
        ], ['Accept' => 'application/json'])->assertOk()->assertJson(['ok' => true]);

        $entry = SiteEntry::query()->firstOrFail();
        $this->assertSame('1990-05-04', $entry->extra['dob'] ?? null);
    }

    public function test_the_date_of_birth_is_optional_and_the_entry_still_succeeds_without_it(): void
    {
        $site = $this->dobSite();

        $this->post($site->url().'/enter', [
            'name' => 'Nodob',
            'email' => 'nodob@example.com',
        ], ['Accept' => 'application/json'])->assertOk()->assertJson(['ok' => true]);

        $this->assertNull(SiteEntry::query()->firstOrFail()->extra);
    }

    public function test_a_posted_date_of_birth_is_ignored_when_the_site_does_not_collect_one(): void
    {
        $site = Site::factory()->create();

        $this->post($site->url().'/enter', [
            'name' => 'Ignored',
            'email' => 'ignored@example.com',
            'dob' => '1990-05-04',
        ], ['Accept' => 'application/json'])->assertOk();

        $this->assertNull(SiteEntry::query()->firstOrFail()->extra);
    }

    public function test_a_future_date_of_birth_is_rejected_and_no_entry_is_stored(): void
    {
        $site = $this->dobSite();

        $this->post($site->url().'/enter', [
            'name' => 'Future',
            'email' => 'future@example.com',
            'dob' => now()->addYear()->toDateString(),
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $this->assertDatabaseCount('site_entries', 0);
    }

    public function test_a_malformed_date_of_birth_is_rejected(): void
    {
        $site = $this->dobSite();

        $this->post($site->url().'/enter', [
            'name' => 'Bad',
            'email' => 'bad@example.com',
            'dob' => 'not-a-date',
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $this->assertDatabaseCount('site_entries', 0);
    }

    public function test_the_add_on_can_be_enabled_and_then_disabled_from_the_manage_form(): void
    {
        $site = Site::factory()->withToken('secret-token')->create();
        $form = [
            'business_name' => $site->inputs['business_name'],
            'prompt' => 'A rewritten description for the business with a fresh seasonal offer now.',
            'type' => 'standard',
        ];

        // Tick it on.
        $this->withCookie($site->cookieName(), 'secret-token')
            ->post(route('microsite.regenerate', $site->slug), [...$form, 'collect_dob' => '1'])
            ->assertRedirect();
        $this->assertTrue($site->refresh()->collectsDateOfBirth());

        // An unticked checkbox sends no key at all, so the saved value must clear.
        $this->withCookie($site->cookieName(), 'secret-token')
            ->post(route('microsite.regenerate', $site->slug), $form)
            ->assertRedirect();
        $this->assertFalse($site->refresh()->collectsDateOfBirth());
    }

    public function test_the_field_renders_on_the_public_page_only_when_enabled(): void
    {
        $site = Site::factory()->create();
        $this->get($site->url())->assertOk()->assertDontSee('name="dob"', false);

        $site->update(['settings' => [...$site->settings, 'collect_dob' => true]]);
        $this->get($site->url())->assertOk()->assertSee('name="dob"', false);
    }

    public function test_an_invalid_date_of_birth_is_rejected_before_any_photo_is_written(): void
    {
        Storage::fake(SiteAsset::DISK);
        $site = $this->dobSite();
        $site->update(['settings' => [...$site->settings, 'collect_dob' => true, 'collect_photo' => true]]);

        $this->post($site->url().'/enter', [
            'name' => 'Orphan',
            'email' => 'orphan@example.com',
            'dob' => now()->addYear()->toDateString(),
            'photo' => UploadedFile::fake()->image('selfie.jpg', 600, 800),
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $this->assertDatabaseCount('site_entries', 0);
        $this->assertSame([], Storage::disk(SiteAsset::DISK)->allFiles());
    }

    public function test_the_csv_export_includes_the_date_of_birth_column(): void
    {
        $site = $this->dobSite();

        $this->post($site->url().'/enter', [
            'name' => 'Pat',
            'email' => 'pat@example.com',
            'dob' => '1990-05-04',
        ], ['Accept' => 'application/json'])->assertOk();

        $response = $this->withCookie($site->cookieName(), 'secret-token')
            ->get(route('microsite.entries.export', $site->slug))
            ->assertOk();

        $csv = $response->streamedContent();
        $this->assertStringContainsString('Date of birth', $csv);
        $this->assertStringContainsString('1990-05-04', $csv);
    }
}
