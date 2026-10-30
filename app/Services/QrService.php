<?php

namespace App\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class QrService
{
    /** An SVG QR code (sharp at any print size). The XML header is removed so it can be placed inside a page. */
    public function svg(string $text, int $size = 320): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle($size), new SvgImageBackEnd()));

        return trim((string) preg_replace('/^<\?xml[^>]*\?>\s*/', '', $writer->writeString($text)));
    }
}
