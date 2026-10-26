<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Models\SiteAsset;
use App\Models\SiteEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhotoEntryTest extends TestCase
{
    use RefreshDatabase;

    private function photoSite(): Site
    {
        $site = Site::factory()->withToken('secret-token')->create();
        $site->update(['settings' => [...$site->settings, 'collect_photo' => true]]);

        return $site;
    }

    public function test_an_entry_can_include_a_photo_stored_on_the_private_disk(): void
    {
        Storage::fake(SiteAsset::DISK);
        $site = $this->photoSite();

        $this->post($site->url().'/enter', [
            'name' => 'Smiley',
            'email' => 'smile@example.com',
            'photo' => UploadedFile::fake()->image('selfie.jpg', 800, 1000),
        ], ['Accept' => 'application/json'])->assertOk()->assertJson(['ok' => true]);

        $entry = SiteEntry::query()->firstOrFail();
        $this->assertNotNull($entry->photo_path);
        $this->assertStringEndsWith('.jpg', $entry->photo_path);
        Storage::disk(SiteAsset::DISK)->assertExists($entry->photo_path);
    }

    public function test_a_non_image_upload_is_rejected_and_no_entry_is_stored(): void
    {
        Storage::fake(SiteAsset::DISK);
        $site = $this->photoSite();

        $this->post($site->url().'/enter', [
            'name' => 'Sneaky',
            'email' => 'sneaky@example.com',
            'photo' => UploadedFile::fake()->create('evil.php', 10, 'application/x-php'),
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $this->assertDatabaseCount('site_entries', 0);
    }

    public function test_a_photo_is_ignored_when_the_site_does_not_collect_one(): void
    {
        Storage::fake(SiteAsset::DISK);
        $site = Site::factory()->create();

        $this->post($site->url().'/enter', [
            'name' => 'NoPhoto',
            'email' => 'np@example.com',
            'photo' => UploadedFile::fake()->image('x.jpg'),
        ], ['Accept' => 'application/json'])->assertOk();

        $this->assertNull(SiteEntry::query()->firstOrFail()->photo_path);
    }

    public function test_an_embedded_payload_is_stripped_by_the_re_encode(): void
    {
        Storage::fake(SiteAsset::DISK);
        $site = $this->photoSite();

        // A real JPEG with a PHP payload appended — a polyglot the re-encode must drop.
        $gd = imagecreatetruecolor(60, 60);
        ob_start();
        imagejpeg($gd);
        $jpeg = (string) ob_get_clean();
        $file = sys_get_temp_dir().'/polyglot-'.uniqid().'.jpg';
        file_put_contents($file, $jpeg."<?php system(\$_GET['c']); ?>");
        $upload = new UploadedFile($file, 'polyglot.jpg', 'image/jpeg', null, true);

        $this->post($site->url().'/enter', [
            'name' => 'Poly', 'email' => 'poly@example.com', 'photo' => $upload,
        ], ['Accept' => 'application/json'])->assertOk();

        $entry = SiteEntry::query()->firstOrFail();
        $stored = Storage::disk(SiteAsset::DISK)->get($entry->photo_path);
        $this->assertStringNotContainsString('<?php', $stored);
    }

    public function test_one_sites_token_cannot_reach_another_sites_entry_photo(): void
    {
        Storage::fake(SiteAsset::DISK);
        $a = $this->photoSite();
        $b = Site::factory()->withToken('other-token')->create();
        $b->update(['settings' => [...$b->settings, 'collect_photo' => true]]);

        $this->post($b->url().'/enter', [
            'name' => 'B', 'email' => 'b@example.com', 'photo' => UploadedFile::fake()->image('b.jpg'),
        ], ['Accept' => 'application/json'])->assertOk();
        $bEntry = $b->entries()->firstOrFail();

        // A's valid token on A's slug, asking for B's entry id → scoped to A → 404.
        $this->withCookie($a->cookieName(), 'secret-token')
            ->get(route('microsite.entry.photo', [$a->slug, $bEntry->getKey()]))
            ->assertNotFound();

        // A wrong token on B's slug → 403 (and reveals nothing about the id).
        $this->withCookie($b->cookieName(), 'secret-token')
            ->get(route('microsite.entry.photo', [$b->slug, $bEntry->getKey()]))
            ->assertForbidden();
    }

    public function test_a_draft_site_stores_no_photo(): void
    {
        Storage::fake(SiteAsset::DISK);
        $site = Site::factory()->withToken('t')->create(['status' => 'draft']);
        $site->update(['settings' => [...$site->settings, 'collect_photo' => true]]);

        $this->post($site->url().'/enter', [
            'name' => 'D', 'email' => 'd@example.com', 'photo' => UploadedFile::fake()->image('d.jpg'),
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $this->assertDatabaseCount('site_entries', 0);
        $this->assertSame([], Storage::disk(SiteAsset::DISK)->allFiles());
    }

    public function test_the_entry_photo_is_served_only_to_the_owner(): void
    {
        Storage::fake(SiteAsset::DISK);
        $site = $this->photoSite();

        $this->post($site->url().'/enter', [
            'name' => 'Smiley',
            'email' => 's@example.com',
            'photo' => UploadedFile::fake()->image('selfie.jpg', 600, 800),
        ], ['Accept' => 'application/json'])->assertOk();
        $entry = SiteEntry::query()->firstOrFail();

        // A stranger with no manage token cannot see the photo.
        $this->get(route('microsite.entry.photo', [$site->slug, $entry]))->assertForbidden();

        // The owner, proven by the manage token, gets the image.
        $this->withCookie($site->cookieName(), 'secret-token')
            ->get(route('microsite.entry.photo', [$site->slug, $entry]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }
}
