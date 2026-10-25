<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesSite;
use App\Models\Site;
use App\Models\SiteAsset;
use App\Support\BannerImage;
use App\Support\QrCode;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Phone handoff: the owner scans the QR on the dashboard, which opens this
 * upload page already authorized (the manage token rides along), and uploads a
 * desktop banner, a mobile banner, a copy deck and a rules document straight
 * from their phone. Banners are redrawn to strip metadata and bound their size;
 * documents are stored as-is and always served through the app with nosniff.
 */
class SiteAssetController extends Controller
{
    use ManagesSite;

    /** Max upload size per kind, in kilobytes. */
    protected const int IMAGE_MAX_KB = 12_288;

    protected const int DOC_MAX_KB = 20_480;

    public function form(Request $request, string $slug): RedirectResponse|View
    {
        $site = Site::query()->where('slug', $slug)->firstOrFail();

        // The QR carries the token once; store it in a cookie and drop it from
        // the address so a shoulder-surfer or history does not keep it.
        if ($request->filled('t') && $site->managedBy($request->query('t'))) {
            return redirect()
                ->route('microsite.upload', $site->slug)
                ->withCookie(cookie($site->cookieName(), (string) $request->query('t'), 60 * 24 * 30));
        }

        $this->authorizeManage($request, $site);

        return view('manage.upload', [
            'site' => $site,
            'slots' => SiteAsset::SLOTS,
            'assets' => $site->assets()->get()->keyBy('slot'),
        ]);
    }

    public function upload(Request $request, string $slug): RedirectResponse
    {
        $site = Site::query()->where('slug', $slug)->firstOrFail();
        $this->authorizeManage($request, $site);

        $stored = 0;

        foreach (SiteAsset::SLOTS as $slot => $spec) {
            if (! $request->hasFile($slot)) {
                continue;
            }

            $spec['kind'] === 'image'
                ? $this->storeImage($request, $site, $slot)
                : $this->storeDoc($request, $site, $slot);

            $stored++;
        }

        $site->touch();

        return back()->with('status', $stored === 0
            ? 'Choose at least one file to upload.'
            : $stored.' '.Str::plural('file', $stored).' uploaded. Your page updates within seconds.');
    }

    public function remove(Request $request, string $slug, string $slot): RedirectResponse
    {
        $site = Site::query()->where('slug', $slug)->firstOrFail();
        $this->authorizeManage($request, $site);

        abort_unless(SiteAsset::isValidSlot($slot), 404);

        if ($asset = $site->asset($slot)) {
            Storage::disk(SiteAsset::DISK)->delete($asset->disk_path);
            $asset->delete();
            $site->touch();
        }

        return back()->with('status', 'Removed.');
    }

    /**
     * Serve an uploaded file. Public slots (banners, rules doc) are open;
     * everything else needs the manage token. Images and PDFs render inline;
     * other documents download. nosniff on everything.
     */
    public function serve(Request $request, string $slug, string $slot): StreamedResponse
    {
        $site = Site::query()->where('slug', $slug)->firstOrFail();

        abort_unless(SiteAsset::isValidSlot($slot), 404);

        $asset = $site->asset($slot);
        abort_if($asset === null, 404);

        if (! $asset->isPublic()) {
            $this->authorizeManage($request, $site);
        }

        $disk = Storage::disk(SiteAsset::DISK);
        abort_unless($disk->exists($asset->disk_path), 404);

        $inline = $asset->isImage() || $asset->mime === 'application/pdf';

        return $disk->response($asset->disk_path, $asset->original_name, [
            'Content-Type' => $asset->mime,
            'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="'.addslashes($asset->original_name).'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => $asset->isPublic() ? 'public, max-age=3600' : 'private, max-age=0, no-store',
        ]);
    }

    protected function storeImage(Request $request, Site $site, string $slot): void
    {
        $request->validate([
            $slot => ['file', 'mimes:jpg,jpeg,png,webp', 'max:'.self::IMAGE_MAX_KB],
        ], [], [$slot => SiteAsset::SLOTS[$slot]['label']]);

        $binary = BannerImage::redraw($request->file($slot), $slot);
        $path = $site->assetFolder().'/'.$slot.'-'.Str::random(24).'.jpg';

        $this->put($site, $slot, $path, $binary, $request->file($slot)->getClientOriginalName(), 'image/jpeg');
    }

    protected function storeDoc(Request $request, Site $site, string $slot): void
    {
        $request->validate([
            $slot => ['file', 'mimes:pdf,doc,docx,png,jpg,jpeg,webp', 'max:'.self::DOC_MAX_KB],
        ], [], [$slot => SiteAsset::SLOTS[$slot]['label']]);

        $file = $request->file($slot);
        $ext = strtolower($file->getClientOriginalExtension() ?: 'pdf');
        $path = $site->assetFolder().'/'.$slot.'-'.Str::random(24).'.'.$ext;

        $this->put($site, $slot, $path, file_get_contents($file->getRealPath()), $file->getClientOriginalName(), $file->getMimeType() ?: 'application/octet-stream');
    }

    /**
     * Write the file and upsert the slot's row, deleting whatever was there.
     */
    protected function put(Site $site, string $slot, string $path, string $binary, string $originalName, string $mime): void
    {
        $previous = $site->asset($slot);

        abort_unless(Storage::disk(SiteAsset::DISK)->put($path, $binary), 503, 'The file could not be saved. Try again.');

        $site->assets()->updateOrCreate(['slot' => $slot], [
            'disk_path' => $path,
            'original_name' => Str::limit($originalName, 180, ''),
            'mime' => $mime,
            'size' => strlen($binary),
        ]);

        if ($previous && $previous->disk_path !== $path) {
            Storage::disk(SiteAsset::DISK)->delete($previous->disk_path);
        }

        $site->unsetRelation('assets');
    }

    /**
     * The QR an open dashboard shows so the owner can jump to this page on their
     * phone, already authorized.
     */
    public static function qrFor(Site $site, string $token): string
    {
        return QrCode::svg(route('microsite.upload', $site->slug).'?t='.$token);
    }
}
