<?php

namespace App\Support;

use App\Models\Site;
use App\Models\SiteAsset;
use GdImage;
use Illuminate\Support\Facades\Storage;

/**
 * Builds the 1200×630 social-share image (og:image) for a site. When the owner
 * has uploaded a desktop banner it is cover-fitted to the card; otherwise a
 * clean branded card is drawn from the site's accent colour, name and headline.
 * Rendered with GD + a bundled font so it works the same locally and on Cloud.
 */
class OgImage
{
    public const int WIDTH = 1200;

    public const int HEIGHT = 630;

    public static function forSite(Site $site): string
    {
        $canvas = imagecreatetruecolor(self::WIDTH, self::HEIGHT);

        $banner = $site->asset(SiteAsset::BANNER_DESKTOP);

        if ($banner !== null && Storage::disk(SiteAsset::DISK)->exists($banner->disk_path)) {
            self::drawBanner($canvas, (string) Storage::disk(SiteAsset::DISK)->get($banner->disk_path));
        } else {
            self::drawCard($canvas, $site);
        }

        ob_start();
        imagepng($canvas);

        return (string) ob_get_clean();
    }

    protected static function drawBanner(GdImage $canvas, string $jpeg): void
    {
        $src = @imagecreatefromstring($jpeg);

        if (! $src instanceof GdImage) {
            imagefill($canvas, 0, 0, imagecolorallocate($canvas, 15, 118, 110));

            return;
        }

        // Cover-fit: scale to fill 1200×630, centre-crop the overflow.
        $sw = imagesx($src);
        $sh = imagesy($src);
        $scale = max(self::WIDTH / $sw, self::HEIGHT / $sh);
        $dw = (int) ceil($sw * $scale);
        $dh = (int) ceil($sh * $scale);
        imagecopyresampled($canvas, $src, (int) ((self::WIDTH - $dw) / 2), (int) ((self::HEIGHT - $dh) / 2), 0, 0, $dw, $dh, $sw, $sh);
    }

    protected static function drawCard(GdImage $canvas, Site $site): void
    {
        [$r, $g, $b] = self::rgb($site->accent());
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, $r, $g, $b));

        // A lighter inset panel so text sits on its own field.
        $panel = imagecolorallocatealpha($canvas, 255, 255, 255, 110);
        imagefilledrectangle($canvas, 60, 60, self::WIDTH - 60, self::HEIGHT - 60, $panel);

        $onAccent = self::isLight($r, $g, $b) ? imagecolorallocate($canvas, 28, 25, 23) : imagecolorallocate($canvas, 255, 255, 255);

        $bold = resource_path('fonts/DejaVuSans-Bold.ttf');
        $regular = resource_path('fonts/DejaVuSans.ttf');

        $business = (string) ($site->inputs['business_name'] ?? '');
        $headline = (string) ($site->content['hero']['headline'] ?? $business);
        $kicker = (string) ($site->content['hero']['kicker'] ?? '');

        imagettftext($canvas, 30, 0, 110, 150, $onAccent, $regular, self::fit($business, 36));

        // Headline, wrapped, large.
        $y = 240;
        foreach (self::wrap($bold, 64, $headline, self::WIDTH - 220, 3) as $line) {
            imagettftext($canvas, 64, 0, 110, $y, $onAccent, $bold, $line);
            $y += 86;
        }

        if ($kicker !== '') {
            imagettftext($canvas, 26, 0, 110, self::HEIGHT - 110, $onAccent, $regular, self::fit($kicker, 48));
        }
    }

    /**
     * Greedy word-wrap for GD text: lines that fit within $maxWidth at the given
     * font size, up to $maxLines (last line ellipsised if it overflows).
     *
     * @return list<string>
     */
    protected static function wrap(string $font, int $size, string $text, int $maxWidth, int $maxLines): array
    {
        $words = preg_split('/\s+/', trim($text)) ?: [];
        $lines = [];
        $line = '';

        foreach ($words as $word) {
            $try = $line === '' ? $word : $line.' '.$word;
            $box = imagettfbbox($size, 0, $font, $try);

            if (($box[2] - $box[0]) > $maxWidth && $line !== '') {
                $lines[] = $line;
                $line = $word;

                if (count($lines) === $maxLines) {
                    return [...array_slice($lines, 0, $maxLines - 1), rtrim($lines[$maxLines - 1], '.').'…'];
                }
            } else {
                $line = $try;
            }
        }

        if ($line !== '') {
            $lines[] = $line;
        }

        return array_slice($lines, 0, $maxLines);
    }

    protected static function fit(string $text, int $max): string
    {
        return mb_strlen($text) > $max ? mb_substr($text, 0, $max - 1).'…' : $text;
    }

    /**
     * @return array{int, int, int}
     */
    protected static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [(int) hexdec(substr($hex, 0, 2)), (int) hexdec(substr($hex, 2, 2)), (int) hexdec(substr($hex, 4, 2))];
    }

    protected static function isLight(int $r, int $g, int $b): bool
    {
        return (0.2126 * $r + 0.7152 * $g + 0.0722 * $b) > 150;
    }
}
