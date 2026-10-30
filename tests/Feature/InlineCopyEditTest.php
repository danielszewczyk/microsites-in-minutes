<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Sites\CopyEdits;
use App\Sites\SiteComposer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class InlineCopyEditTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'inline-edit-token';

    private function draft(): Site
    {
        return Site::factory()->withToken(self::TOKEN)->create(['status' => Site::STATUS_DRAFT]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function patchAsOwner(Site $site, array $payload): TestResponse
    {
        return $this->withCredentials()->withCookie($site->cookieName(), self::TOKEN)
            ->patchJson(route('microsite.content.update', $site->slug), $payload);
    }

    public function test_an_edit_is_refused_without_the_manage_cookie(): void
    {
        $site = $this->draft();

        $this->patchJson(route('microsite.content.update', $site->slug), ['path' => 'hero.headline', 'text' => 'New'])
            ->assertForbidden();
        $this->deleteJson(route('microsite.content.reset', $site->slug), ['path' => 'hero.headline'])
            ->assertForbidden();

        $this->assertNull($site->fresh()->content_overrides);
    }

    public function test_an_edit_is_refused_with_a_wrong_cookie(): void
    {
        $site = $this->draft();

        $this->withCredentials()->withCookie($site->cookieName(), 'not-the-token')
            ->patchJson(route('microsite.content.update', $site->slug), ['path' => 'hero.headline', 'text' => 'New'])
            ->assertForbidden();

        $this->assertNull($site->fresh()->content_overrides);
    }

    public function test_a_published_site_refuses_edits_even_from_its_owner(): void
    {
        $site = Site::factory()->withToken(self::TOKEN)->create();

        $this->patchAsOwner($site, ['path' => 'hero.headline', 'text' => 'Live edit'])->assertStatus(409);
        $this->withCredentials()->withCookie($site->cookieName(), self::TOKEN)
            ->deleteJson(route('microsite.content.reset', $site->slug), ['path' => 'hero.headline'])
            ->assertStatus(409);

        $this->assertNull($site->fresh()->content_overrides);
    }

    public function test_an_edit_must_be_sent_as_json(): void
    {
        $site = $this->draft();

        // A form post is what a cross-site page could send without a preflight.
        $this->withCookie($site->cookieName(), self::TOKEN)
            ->patch(route('microsite.content.update', $site->slug), ['path' => 'hero.headline', 'text' => 'New'])
            ->assertStatus(415);

        $this->assertNull($site->fresh()->content_overrides);
    }

    public function test_paths_outside_the_allowlist_are_rejected(): void
    {
        $site = $this->draft();

        foreach (['meta.title', 'track.hero_cta', 'rules.sponsor', 'features.0', 'features.9.title', 'hero', '../x', 'contact.email'] as $path) {
            $this->patchAsOwner($site, ['path' => $path, 'text' => 'Sneaky'])->assertStatus(422);
        }

        $this->assertNull($site->fresh()->content_overrides);
    }

    public function test_over_long_text_and_empty_text_are_rejected(): void
    {
        $site = $this->draft();

        $this->patchAsOwner($site, ['path' => 'cta.label', 'text' => str_repeat('x', CopyEdits::FIELDS['cta.label']['max'] + 1)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('text');

        $this->patchAsOwner($site, ['path' => 'cta.label', 'text' => "  \t  "])
            ->assertStatus(422);

        $this->assertNull($site->fresh()->content_overrides);
    }

    public function test_a_saved_edit_is_stored_as_plain_text_and_shown_on_the_page_escaped(): void
    {
        $site = $this->draft();

        $this->patchAsOwner($site, ['path' => 'hero.headline', 'text' => "  Fresh <b>bagels</b>\nevery   morning  "])
            ->assertOk()
            ->assertJson(['ok' => true, 'path' => 'hero.headline', 'text' => 'Fresh <b>bagels</b> every morning', 'edited' => true]);

        $this->assertSame(['hero.headline' => 'Fresh <b>bagels</b> every morning'], $site->fresh()->content_overrides);

        $this->get($site->url())
            ->assertOk()
            ->assertSee('Fresh &lt;b&gt;bagels&lt;/b&gt; every morning', false)
            ->assertDontSee('<b>bagels</b>', false);
    }

    public function test_the_edit_shows_on_every_element_bound_to_the_path(): void
    {
        $site = $this->draft();

        $this->patchAsOwner($site, ['path' => 'cta.label', 'text' => 'Book a table'])->assertOk();

        // The CTA label is rendered on the hero button and the form's submit.
        $html = $this->get($site->url())->assertOk()->getContent();
        $this->assertSame(2, substr_count($html, '>Book a table<'));
    }

    public function test_an_override_survives_regeneration(): void
    {
        $site = $this->draft();
        $this->patchAsOwner($site, ['path' => 'hero.headline', 'text' => "The owner's own words"])->assertOk();

        $site = app(SiteComposer::class)->regenerate($site->fresh(), [
            'business_name' => 'Renamed Business',
            'prompt' => 'A completely new prompt that regenerates all of the copy.',
        ]);

        $this->assertSame('Renamed Business', $site->content['hero']['headline'], 'the generated layer was replaced');
        $this->assertSame("The owner's own words", $site->renderedContent()['hero']['headline'], 'the override stays on top');
        $this->get($site->url())->assertOk()->assertSee('The owner&#039;s own words', false);
    }

    public function test_a_stale_override_is_not_invented_into_the_page(): void
    {
        $site = $this->draft();
        $site->update(['content_overrides' => ['offer.bullets.5' => 'A bullet that no longer exists']]);

        $this->assertArrayNotHasKey('bullets', array_filter($site->renderedContent()['offer']['bullets'] ?? []));
        $this->get($site->url())->assertOk()->assertDontSee('A bullet that no longer exists');
    }

    public function test_reset_removes_the_override_and_restores_the_generated_text(): void
    {
        $site = $this->draft();
        $generated = $site->content['hero']['headline'];
        $this->patchAsOwner($site, ['path' => 'hero.headline', 'text' => 'Changed'])->assertOk();

        $this->withCredentials()->withCookie($site->cookieName(), self::TOKEN)
            ->deleteJson(route('microsite.content.reset', $site->slug), ['path' => 'hero.headline'])
            ->assertOk()
            ->assertJson(['ok' => true, 'path' => 'hero.headline', 'text' => $generated, 'edited' => false]);

        $this->assertNull($site->fresh()->content_overrides);
        $this->get($site->url())->assertOk()->assertSee(e($generated), false)->assertDontSee('>Changed<', false);
    }

    public function test_saving_bumps_the_version_and_fresh_html_is_served(): void
    {
        $site = $this->draft();
        $before = $site->version();

        // Prime the HTML cache with the generated copy.
        $this->get($site->url())->assertOk()->assertDontSee('Brand new headline');

        $response = $this->patchAsOwner($site, ['path' => 'hero.headline', 'text' => 'Brand new headline'])->assertOk();

        $this->assertNotSame($before, $site->fresh()->version());
        $this->assertSame($site->fresh()->version(), $response->json('version'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $this->get($site->url())->assertOk()->assertSee('Brand new headline');
    }

    public function test_the_draft_page_carries_the_edit_marks_and_controls_without_the_token(): void
    {
        $site = $this->draft();

        $response = $this->get($site->url())->assertOk();

        foreach (['hero.headline', 'hero.subhead', 'cta.label', 'cta.note', 'offer.heading', 'offer.body', 'features.0.title', 'features.0.text', 'features.2.text', 'about', 'form.heading', 'form.note'] as $path) {
            $response->assertSee('data-edit="'.$path.'"', false);
        }

        $response
            ->assertSee('class="preview-edit"', false)
            ->assertSee("box.className = 'mim-editor'", false)
            ->assertSee(route('microsite.content.update', $site->slug, absolute: false), false)
            ->assertSee(route('microsite.content.reset', $site->slug, absolute: false), false)
            ->assertDontSee(self::TOKEN);
    }

    public function test_a_published_page_has_no_editor(): void
    {
        $site = Site::factory()->withToken(self::TOKEN)->create(['content_overrides' => ['hero.headline' => 'Edited headline']]);

        $this->get($site->url())
            ->assertOk()
            // The override still renders on the live page...
            ->assertSee('Edited headline')
            // ...but nothing of the editor does.
            ->assertDontSee('data-edit=', false)
            ->assertDontSee('preview-edit', false)
            ->assertDontSee('mim-editor', false)
            ->assertDontSee('manage/content', false);
    }

    public function test_the_embed_and_og_image_use_the_edited_copy(): void
    {
        $site = $this->draft();
        $this->patchAsOwner($site, ['path' => 'form.heading', 'text' => 'Join the list'])->assertOk();

        $this->get(route('microsite.embed', $site->slug))->assertOk()->assertSee('Join the list');
    }
}
