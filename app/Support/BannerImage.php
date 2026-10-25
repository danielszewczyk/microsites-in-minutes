<?php

namespace App\Support;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Turns an uploaded banner into a clean, bounded JPEG. An image is never stored
 * as sent: it is decoded and drawn again, which drops its metadata (camera,
 * GPS) and anything else hiding in the file, and caps its dimensions so one
 * upload cannot blow a worker's memory.
 */
class BannerImage
{
    /** The longest edge a stored banner keeps. */
    public const int MAX_EDGE = 2400;

    /** The largest image the server will decode at all (GD holds ~4 bytes/pixel). */
    public const int MAX_PIXELS = 16_000_000;

    public static function redraw(UploadedFile $file, string $field = 'file'): string
    {
        $path = $file->getRealPath();
        $size = @getimagesize($path);

        if ($size === false) {
            throw ValidationException::withMessages([$field => 'That file could not be read as an image.']);
        }

        if ($size[0] * $size[1] > self::MAX_PIXELS) {
            throw ValidationException::withMessages([$field => 'That image is too large; please use a smaller one.']);
        }

        $source = @imagecreatefromstring((string) file_get_contents($path));

        if (! $source instanceof GdImage) {
            throw ValidationException::withMessages([$field => 'That file could not be read as an image.']);
        }

        $source = self::upright($source, $file, (int) ($size[2] ?? 0));

        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, self::MAX_EDGE / max($width, $height));
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($newWidth, $newHeight);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        ob_start();
        imagejpeg($canvas, null, 82);

        return (string) ob_get_clean();
    }

    /**
     * Turn a phone photo the way its camera says it was held. Only JPEGs carry
     * orientation, and only when the EXIF extension is present.
     */
    protected static function upright(GdImage $image, UploadedFile $file, int $type): GdImage
    {
        if ($type !== IMAGETYPE_JPEG || ! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($file->getRealPath());
        $orientation = (int) ($exif['Orientation'] ?? 1);

        $degrees = match ($orientation) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        return $degrees === 0 ? $image : imagerotate($image, $degrees, 0);
    }
}
