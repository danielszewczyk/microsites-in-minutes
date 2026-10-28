<?php

namespace Tests\Feature;

use App\Support\BannerImage;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class BannerImageTest extends TestCase
{
    /**
     * A large, high-frequency (noisy) image doesn't compress well at full
     * quality, so the automatic step-down must kick in and produce a JPEG
     * smaller than a plain high-quality encode of the same pixels (#21).
     */
    public function test_a_large_image_is_compressed_below_a_plain_high_quality_encode(): void
    {
        [$path, $canvas] = $this->makeNoisyJpeg(1600, 900);

        // Baseline: the same canvas encoded once at the starting quality.
        ob_start();
        imagejpeg($canvas, null, 82);
        $baseline = (string) ob_get_clean();
        imagedestroy($canvas);

        // The baseline must exceed the target, or there'd be nothing to prove.
        $this->assertGreaterThan(BannerImage::TARGET_BYTES, strlen($baseline));

        $out = BannerImage::redraw(new UploadedFile($path, 'noise.jpg', 'image/jpeg', null, true), 'banner');
        @unlink($path);

        // It's still a valid JPEG, within the dimension bound...
        $info = getimagesizefromstring($out);
        $this->assertNotFalse($info);
        $this->assertSame(IMAGETYPE_JPEG, $info[2]);
        $this->assertLessThanOrEqual(BannerImage::MAX_EDGE, max($info[0], $info[1]));

        // ...and the step-down produced a smaller file than plain quality 82.
        $this->assertLessThan(strlen($baseline), strlen($out));
    }

    /**
     * Build a noisy JPEG on disk and return [path, canvasCopy]. Random pixels
     * give the high-frequency content a flat fake image lacks.
     *
     * @return array{0: string, 1: \GdImage}
     */
    protected function makeNoisyJpeg(int $w, int $h): array
    {
        mt_srand(1234);
        $img = imagecreatetruecolor($w, $h);

        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                imagesetpixel($img, $x, $y, mt_rand(0, 0xFFFFFF));
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'mimnoise').'.jpg';
        imagejpeg($img, $path, 95);

        return [$path, $img];
    }
}
