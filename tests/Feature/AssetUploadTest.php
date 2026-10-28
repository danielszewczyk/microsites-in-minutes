<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Models\SiteAsset;
use App\Support\BannerImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AssetUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(SiteAsset::DISK);
    }

    public function test_upload_page_needs_the_token(): void
    {
        $site = Site::factory()->withToken('tok')->create();

        $this->get(route('microsite.upload', $site->slug))->assertForbidden();

        $this->get(route('microsite.upload', $site->slug).'?t=tok')
            ->assertRedirect(route('microsite.upload', $site->slug))
            ->assertCookie($site->cookieName(), 'tok');
    }

    public function test_banner_and_doc_upload_and_attach(): void
    {
        $site = Site::factory()->withToken('tok')->create();

        $this->withCookie($site->cookieName(), 'tok')->post(route('microsite.upload.store', $site->slug), [
            SiteAsset::BANNER_DESKTOP => UploadedFile::fake()->image('wide.jpg', 1600, 500),
            SiteAsset::RULES_DOC => UploadedFile::fake()->create('rules.pdf', 40, 'application/pdf'),
        ])->assertRedirect();

        $this->assertDatabaseHas('site_assets', ['site_id' => $site->id, 'slot' => SiteAsset::BANNER_DESKTOP]);
        $this->assertDatabaseHas('site_assets', ['site_id' => $site->id, 'slot' => SiteAsset::RULES_DOC]);

        $banner = $site->assets()->where('slot', SiteAsset::BANNER_DESKTOP)->first();
        Storage::disk(SiteAsset::DISK)->assertExists($banner->disk_path);
        // Banner is re-encoded to JPEG (metadata stripped).
        $this->assertSame('image/jpeg', $banner->mime);
        $this->assertStringEndsWith('.jpg', $banner->disk_path);
    }

    public function test_banner_shows_on_the_public_page(): void
    {
        $site = Site::factory()->withToken('tok')->create();
        $this->withCookie($site->cookieName(), 'tok')->post(route('microsite.upload.store', $site->slug), [
            SiteAsset::BANNER_DESKTOP => UploadedFile::fake()->image('wide.jpg', 1600, 500),
        ]);

        $this->get($site->url())
            ->assertOk()
            ->assertSee(route('microsite.asset', [$site->slug, SiteAsset::BANNER_DESKTOP], absolute: false), false);
    }

    public function test_public_banner_is_served_but_private_copy_deck_needs_the_token(): void
    {
        // Seed the assets directly so no manage cookie is set (withCookie would
        // persist across requests in a test and defeat the unauthorized check).
        $site = Site::factory()->withToken('tok')->create();
        foreach ([SiteAsset::BANNER_DESKTOP => 'image/jpeg', SiteAsset::COPY_DECK => 'application/pdf'] as $slot => $mime) {
            $path = $site->assetFolder().'/'.$slot.'.bin';
            Storage::disk(SiteAsset::DISK)->put($path, 'data');
            $site->assets()->create(['slot' => $slot, 'disk_path' => $path, 'original_name' => $slot, 'mime' => $mime, 'size' => 4]);
        }

        // Banner is public; copy deck is private.
        $this->get(route('microsite.asset', [$site->slug, SiteAsset::BANNER_DESKTOP]))->assertOk();
        $this->get(route('microsite.asset', [$site->slug, SiteAsset::COPY_DECK]))->assertForbidden();

        $this->withCookie($site->cookieName(), 'tok')
            ->get(route('microsite.asset', [$site->slug, SiteAsset::COPY_DECK]))->assertOk();
    }

    public function test_a_script_disguised_as_an_image_is_rejected(): void
    {
        $site = Site::factory()->withToken('tok')->create();

        $this->withCookie($site->cookieName(), 'tok')->post(route('microsite.upload.store', $site->slug), [
            SiteAsset::BANNER_DESKTOP => UploadedFile::fake()->createWithContent('evil.jpg', '<?php echo "x"; ?>'),
        ])->assertSessionHasErrors(SiteAsset::BANNER_DESKTOP);

        $this->assertDatabaseCount('site_assets', 0);
    }

    public function test_replacing_a_slot_deletes_the_old_file(): void
    {
        $site = Site::factory()->withToken('tok')->create();
        $this->withCookie($site->cookieName(), 'tok')->post(route('microsite.upload.store', $site->slug), [
            SiteAsset::BANNER_MOBILE => UploadedFile::fake()->image('a.jpg', 400, 400),
        ]);
        $first = $site->assets()->where('slot', SiteAsset::BANNER_MOBILE)->first()->disk_path;

        $this->withCookie($site->cookieName(), 'tok')->post(route('microsite.upload.store', $site->slug), [
            SiteAsset::BANNER_MOBILE => UploadedFile::fake()->image('b.jpg', 400, 400),
        ]);

        Storage::disk(SiteAsset::DISK)->assertMissing($first);
        $this->assertSame(1, $site->assets()->where('slot', SiteAsset::BANNER_MOBILE)->count());
    }

    public function test_uploaded_banners_are_compressed_under_the_target_size(): void
    {
        $site = Site::factory()->withToken('tok')->create();

        $this->withCookie($site->cookieName(), 'tok')->post(route('microsite.upload.store', $site->slug), [
            SiteAsset::BANNER_DESKTOP => UploadedFile::fake()->image('big.jpg', 2400, 1260),
        ])->assertRedirect();

        $banner = $site->assets()->where('slot', SiteAsset::BANNER_DESKTOP)->first();

        // Automatic compression bounds the stored size regardless of the upload.
        $this->assertGreaterThan(0, $banner->size);
        $this->assertLessThanOrEqual(BannerImage::TARGET_BYTES, $banner->size);
    }

    public function test_upload_changes_the_site_version(): void
    {
        $site = Site::factory()->withToken('tok')->create();
        $before = $site->version();

        $this->withCookie($site->cookieName(), 'tok')->post(route('microsite.upload.store', $site->slug), [
            SiteAsset::BANNER_DESKTOP => UploadedFile::fake()->image('a.jpg', 400, 400),
        ]);

        $this->assertNotSame($before, $site->fresh()->version());
    }
}
