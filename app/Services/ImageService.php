<?php

namespace App\Services;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Re-encodes every uploaded photo to WebP (max 1600px wide) plus a 480px thumbnail.
 * Re-encoding also drops EXIF data and anything hidden inside the original file.
 */
class ImageService
{
    private const FULL_WIDTH = 1600;
    private const THUMB_WIDTH = 480;
    private const QUALITY = 82;

    public function store(UploadedFile $file, string $dir): string
    {
        $source = $this->canProcess() ? $this->load($file) : null;

        if (! $source) {
            return $file->store($dir, 'public'); // GD unavailable: keep the validated original
        }

        $name = Str::random(40);
        $disk = Storage::disk('public');

        $disk->put("{$dir}/{$name}.webp", $this->encode($this->resize($source, self::FULL_WIDTH)));
        $disk->put("{$dir}/{$name}-" . self::THUMB_WIDTH . '.webp', $this->encode($this->resize($source, self::THUMB_WIDTH)));

        return "{$dir}/{$name}.webp";
    }

    public function delete(?string $path): void
    {
        if (! $path) {
            return;
        }

        $disk = Storage::disk('public');
        $disk->delete($path);

        if (str_ends_with($path, '.webp')) {
            $disk->delete(substr($path, 0, -5) . '-' . self::THUMB_WIDTH . '.webp');
        }
    }

    private function canProcess(): bool
    {
        return extension_loaded('gd') && function_exists('imagewebp');
    }

    private function load(UploadedFile $file): ?GdImage
    {
        $path = $file->getRealPath();
        $info = @getimagesize($path);

        if (! $info) {
            return null;
        }

        $image = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            default => false,
        };

        if (! $image) {
            return null;
        }

        // Phone photos are often stored sideways with an EXIF flag. Apply it before the flag is dropped.
        if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $orientation = @exif_read_data($path)['Orientation'] ?? 1;
            $rotated = match ($orientation) {
                3 => imagerotate($image, 180, 0),
                6 => imagerotate($image, -90, 0),
                8 => imagerotate($image, 90, 0),
                default => $image,
            };
            $image = $rotated ?: $image;
        }

        return $image;
    }

    private function resize(GdImage $src, int $maxWidth): GdImage
    {
        $w = imagesx($src);
        $h = imagesy($src);
        $newW = min($w, $maxWidth);
        $newH = (int) round($h * $newW / $w);

        $dst = imagecreatetruecolor($newW, $newH);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);

        return $dst;
    }

    private function encode(GdImage $image): string
    {
        ob_start();
        imagewebp($image, null, self::QUALITY);

        return (string) ob_get_clean();
    }

    /** Square PNG for site icons. Browsers handle PNG favicons everywhere, unlike WebP in older Safari. */
    public function storeIcon(UploadedFile $file, string $dir, int $size = 256): string
    {
        $source = extension_loaded('gd') ? $this->load($file) : null;

        if (! $source) {
            return $file->store($dir, 'public');
        }

        $w = imagesx($source);
        $h = imagesy($source);
        $side = min($w, $h);

        $dst = imagecreatetruecolor($size, $size);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagecopyresampled($dst, $source, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), $size, $size, $side, $side);

        ob_start();
        imagepng($dst);
        $png = (string) ob_get_clean();

        $path = $dir . '/' . Str::random(40) . '.png';
        Storage::disk('public')->put($path, $png);

        return $path;
    }
}
