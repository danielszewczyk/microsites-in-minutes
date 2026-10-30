<?php

namespace Tests\Feature;

use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PreviewManageButtonTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_owner_check_passes_with_the_manage_cookie_and_is_never_cached(): void
    {
        $site = Site::factory()->withToken('secret-manage-token-xyz')->create(['status' => Site::STATUS_DRAFT]);

        $response = $this->withCookie($site->cookieName(), 'secret-manage-token-xyz')
            ->get(route('microsite.manage.check', $site->slug));

        $response->assertNoContent();
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_the_owner_check_refuses_a_browser_without_the_cookie(): void
    {
        $site = Site::factory()->withToken('secret-manage-token-xyz')->create(['status' => Site::STATUS_DRAFT]);

        $this->get(route('microsite.manage.check', $site->slug))->assertForbidden();
    }

    public function test_the_owner_check_refuses_a_wrong_cookie(): void
    {
        $site = Site::factory()->withToken('secret-manage-token-xyz')->create(['status' => Site::STATUS_DRAFT]);

        $this->withCookie($site->cookieName(), 'not-the-token')
            ->get(route('microsite.manage.check', $site->slug))
            ->assertForbidden();
    }

    public function test_the_draft_page_links_to_manage_without_exposing_the_token(): void
    {
        $site = Site::factory()->withToken('secret-manage-token-xyz')->create(['status' => Site::STATUS_DRAFT]);

        $this->get($site->url())
            ->assertOk()
            ->assertSee('class="preview-manage"', false)
            ->assertSee(route('microsite.manage', $site->slug, absolute: false), false)
            ->assertSee(route('microsite.manage.check', $site->slug, absolute: false), false)
            // The button is revealed only when the ownership check succeeds.
            ->assertSee("if (r.ok) { document.getElementById('preview-bar').classList.add('is-owner'); }", false)
            ->assertDontSee('secret-manage-token-xyz')
            ->assertDontSee('?t=', false);
    }

    public function test_a_published_page_has_no_manage_link(): void
    {
        $site = Site::factory()->withToken('secret-manage-token-xyz')->create();

        // The stylesheet is shared, so check for the button markup and the
        // ownership-check script themselves, not the class names.
        $this->get($site->url())
            ->assertOk()
            ->assertDontSee('class="preview-manage"', false)
            ->assertDontSee('manage/check', false);
    }
}
