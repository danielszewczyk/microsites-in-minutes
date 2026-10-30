<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Models\SiteEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComplianceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_draft_is_noindex_shows_a_banner_and_refuses_entries(): void
    {
        $site = Site::factory()->create(['status' => Site::STATUS_DRAFT]);

        $this->get($site->url())
            ->assertOk()
            ->assertSee('noindex, nofollow', false)
            ->assertSee('Preview')
            ->assertSee('Not live and not accepting entries');

        $this->postJson($site->url().'/enter', ['name' => 'Jamie', 'email' => 'jamie@example.com'])
            ->assertStatus(422)
            ->assertJson(['ok' => false]);

        $this->assertDatabaseCount('site_entries', 0);
    }

    public function test_a_draft_is_left_out_of_the_sitemap(): void
    {
        $published = Site::factory()->create();
        $draft = Site::factory()->create(['status' => Site::STATUS_DRAFT]);

        $this->get('/sitemap.xml')
            ->assertSee($published->url(), false)
            ->assertDontSee($draft->url(), false);
    }

    public function test_entries_outside_the_date_window_are_refused(): void
    {
        $closed = Site::factory()->sweepstakes()->create();
        $closed->update(['inputs' => [...$closed->inputs, 'start_date' => '2020-01-01', 'end_date' => '2020-01-31']]);

        $this->postJson($closed->url().'/enter', ['name' => 'Jamie', 'email' => 'jamie@example.com'])
            ->assertStatus(422);

        $notYet = Site::factory()->sweepstakes()->create();
        $notYet->update(['inputs' => [...$notYet->inputs, 'start_date' => now()->addYear()->toDateString(), 'end_date' => now()->addYear()->addMonth()->toDateString()]]);

        $this->postJson($notYet->url().'/enter', ['name' => 'Jamie', 'email' => 'jamie@example.com'])
            ->assertStatus(422);

        $this->assertDatabaseCount('site_entries', 0);
    }

    public function test_one_entry_per_email(): void
    {
        $site = Site::factory()->create();

        $this->postJson($site->url().'/enter', ['name' => 'Jamie', 'email' => 'JAMIE@example.com'])->assertOk();
        $this->postJson($site->url().'/enter', ['name' => 'Jamie Again', 'email' => 'jamie@example.com'])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertSame(1, $site->fresh()->entry_count);
        $this->assertDatabaseCount('site_entries', 1);
    }

    public function test_csv_export_neutralises_formula_injection(): void
    {
        $site = Site::factory()->withToken('tok')->create();
        SiteEntry::factory()->for($site)->create(['name' => '=HYPERLINK("http://evil","x")', 'email' => 'a@example.com']);

        $content = $this->withCookie($site->cookieName(), 'tok')
            ->get(route('microsite.entries.export', $site->slug))
            ->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $content);
        $this->assertStringNotContainsString(',=HYPERLINK', $content);
    }
}
