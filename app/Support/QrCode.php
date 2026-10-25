<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Renders a URL as an inline SVG QR code. The XML declaration and namespace are
 * dropped: HTML does not need them, and the markup carries no URL but the one
 * passed in.
 */
class QrCode
{
    public static function svg(string $url): string
    {
        $svg = (new Writer(new ImageRenderer(new RendererStyle(160, 1), new SvgImageBackEnd)))->writeString($url);

        return trim(str_replace(
            ' xmlns="http://www.w3.org/2000/svg" version="1.1"',
            '',
            (string) preg_replace('/^<\?xml[^>]*\?>/', '', $svg),
        ));
    }
}
