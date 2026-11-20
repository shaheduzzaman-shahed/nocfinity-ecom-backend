<?php

namespace Database\Seeders;

use App\Models\ProductImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class ProductImagePlaceholderSeeder extends Seeder
{
    public function run(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            return;
        }

        $disk = Storage::disk('public');

        ProductImage::query()
            ->with('product:id,name')
            ->orderBy('id')
            ->each(function (ProductImage $image) use ($disk): void {
                $path = $image->image_url;

                if (
                    ! $path
                    || str_starts_with($path, 'http://')
                    || str_starts_with($path, 'https://')
                    || $disk->exists($path)
                ) {
                    return;
                }

                $target = $disk->path($path);

                if (! is_dir(dirname($target))) {
                    mkdir(dirname($target), 0775, true);
                }

                $this->render(
                    $target,
                    $image->product?->name ?? 'Nocfinity',
                    crc32($path),
                );
            });
    }

    private function render(string $target, string $label, int $seed): void
    {
        $width = 800;
        $height = 1000;
        $canvas = imagecreatetruecolor($width, $height);

        $base = abs($seed) % 360;
        $top = $this->hsvToRgb($base, 0.35, 0.55);
        $bottom = $this->hsvToRgb(($base + 24) % 360, 0.45, 0.3);

        for ($y = 0; $y < $height; $y++) {
            $ratio = $y / $height;
            $color = imagecolorallocate(
                $canvas,
                (int) round($top[0] + ($bottom[0] - $top[0]) * $ratio),
                (int) round($top[1] + ($bottom[1] - $top[1]) * $ratio),
                (int) round($top[2] + ($bottom[2] - $top[2]) * $ratio),
            );

            imageline($canvas, 0, $y, $width, $y, $color);
        }

        $white = imagecolorallocate($canvas, 255, 255, 255);
        $muted = imagecolorallocate($canvas, 230, 230, 230);

        imagestring($canvas, 5, 36, 40, 'NOCFINITY', $muted);

        $text = mb_strtoupper(mb_substr($label, 0, 32));
        $fontWidth = imagefontwidth(5);
        $x = max(36, (int) (($width - strlen($text) * $fontWidth) / 2));

        imagestring($canvas, 5, $x, (int) ($height / 2) - 10, $text, $white);

        imagejpeg($canvas, $target, 86);
        imagedestroy($canvas);
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function hsvToRgb(float $hue, float $saturation, float $value): array
    {
        $chroma = $value * $saturation;
        $section = $hue / 60;
        $x = $chroma * (1 - abs(fmod($section, 2) - 1));
        $m = $value - $chroma;

        [$r, $g, $b] = match (true) {
            $section < 1 => [$chroma, $x, 0],
            $section < 2 => [$x, $chroma, 0],
            $section < 3 => [0, $chroma, $x],
            $section < 4 => [0, $x, $chroma],
            $section < 5 => [$x, 0, $chroma],
            default => [$chroma, 0, $x],
        };

        return [
            (int) round(($r + $m) * 255),
            (int) round(($g + $m) * 255),
            (int) round(($b + $m) * 255),
        ];
    }
}
