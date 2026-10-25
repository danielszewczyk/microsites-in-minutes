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

    /**
     * The product's own 1200×630 share card for the landing page — a branded
     * teal card with the wordmark, tagline and a "prompt → live site" hint.
     * Distinct from forSite(), which draws a card for one generated microsite.
     */
    public static function forHome(): string
    {
        $canvas = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        self::drawHomeCard($canvas);

        ob_start();
        imagepng($canvas);

        return (string) ob_get_clean();
    }

    /**
     * A square brand mark (apple-touch-icon): a full-bleed teal tile with the
     * white dot. Full-bleed and opaque because iOS masks its own rounded
     * corners and paints anything transparent black.
     */
    public static function homeIcon(int $size = 180): string
    {
        $canvas = imagecreatetruecolor($size, $size);

        [$r, $g, $b] = self::rgb(Site::DEFAULT_ACCENT);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, $r, $g, $b));
        $dot = (int) ($size * 0.42);
        imagefilledellipse($canvas, (int) ($size / 2), (int) ($size / 2), $dot, $dot, imagecolorallocate($canvas, 255, 255, 255));

        ob_start();
        imagepng($canvas);

        return (string) ob_get_clean();
    }

    protected static function drawHomeCard(GdImage $canvas): void
    {
        // Vertical gradient from the brand accent down to a deeper teal.
        [$tr, $tg, $tb] = self::rgb(Site::DEFAULT_ACCENT);
        [$br, $bg, $bb] = [11, 74, 69];

        for ($y = 0; $y < self::HEIGHT; $y++) {
            $t = $y / self::HEIGHT;
            $line = imagecolorallocate(
                $canvas,
                (int) round($tr + ($br - $tr) * $t),
                (int) round($tg + ($bg - $tg) * $t),
                (int) round($tb + ($bb - $tb) * $t),
            );
            imageline($canvas, 0, $y, self::WIDTH, $y, $line);
        }

        $white = imagecolorallocate($canvas, 255, 255, 255);
        $soft = imagecolorallocate($canvas, 198, 231, 226);

        $bold = resource_path('fonts/DejaVuSans-Bold.ttf');
        $regular = resource_path('fonts/DejaVuSans.ttf');

        $x = 90;

        imagettftext($canvas, 22, 0, $x, 108, $soft, $bold, 'MICROSITESINMINUTES.COM');

        $y = 232;
        foreach (self::wrap($bold, 76, 'Microsites in Minutes', self::WIDTH - 180, 2) as $line) {
            imagettftext($canvas, 76, 0, $x, $y, $white, $bold, $line);
            $y += 98;
        }

        imagettftext($canvas, 30, 0, $x, $y + 16, $soft, $regular, 'A polished, mobile-first promo site in minutes.');

        self::drawFlowPills($canvas, $x, self::HEIGHT - 92, $bold, $regular);
    }

    /**
     * The "Your prompt → Live site" chip row that hints at how it works.
     */
    protected static function drawFlowPills(GdImage $canvas, int $x, int $cy, string $bold, string $regular): void
    {
        $white = imagecolorallocate($canvas, 255, 255, 255);
        $ink = imagecolorallocate($canvas, 13, 74, 69);

        $end = self::pill($canvas, $x, $cy, 'Your prompt', $regular, 24, $white, $ink);
        imagettftext($canvas, 32, 0, $end + 24, $cy + 12, $white, $bold, '→');
        self::pill($canvas, $end + 80, $cy, 'Live site in seconds', $regular, 24, $white, $ink);
    }

    /**
     * A rounded "pill" sized to its text width, drawn centred on $cy. Every
     * pill at a given size gets the same height (from a reference set of
     * ascenders + descenders) so a row of them lines up, and the text is
     * vertically centred within it. Returns the right-edge x so the next
     * element can follow.
     */
    protected static function pill(GdImage $canvas, int $x, int $cy, string $text, string $font, int $size, int $bg, int $fg): int
    {
        $padX = 28;
        $padY = 16;

        $reference = imagettfbbox($size, 0, $font, 'Agjpqy');
        $height = ($reference[1] - $reference[7]) + $padY * 2;

        $box = imagettfbbox($size, 0, $font, $text);
        $width = ($box[2] - $box[0]) + $padX * 2;
        $top = $cy - (int) ($height / 2);

        self::roundedRect($canvas, $x, $top, $x + $width, $top + $height, (int) ($height / 2), $bg);

        // Baseline that centres this text's own ink on $cy.
        $baseline = $cy - (int) (($box[1] + $box[7]) / 2);
        imagettftext($canvas, $size, 0, $x + $padX - $box[0], $baseline, $fg, $font, $text);

        return $x + $width;
    }

    protected static function roundedRect(GdImage $canvas, int $x1, int $y1, int $x2, int $y2, int $radius, int $color): void
    {
        $diameter = $radius * 2;
        imagefilledrectangle($canvas, $x1 + $radius, $y1, $x2 - $radius, $y2, $color);
        imagefilledrectangle($canvas, $x1, $y1 + $radius, $x2, $y2 - $radius, $color);
        imagefilledellipse($canvas, $x1 + $radius, $y1 + $radius, $diameter, $diameter, $color);
        imagefilledellipse($canvas, $x2 - $radius, $y1 + $radius, $diameter, $diameter, $color);
        imagefilledellipse($canvas, $x1 + $radius, $y2 - $radius, $diameter, $diameter, $color);
        imagefilledellipse($canvas, $x2 - $radius, $y2 - $radius, $diameter, $diameter, $color);
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
