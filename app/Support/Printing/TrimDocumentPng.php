<?php

namespace App\Support\Printing;

/** Trim only the PNG share/copy representation; printed A4 pages are untouched. */
final class TrimDocumentPng
{
    public function handle(string $png): string
    {
        $image = @imagecreatefromstring($png);
        if (! $image instanceof \GdImage) {
            return $png;
        }

        try {
            if (! imageistruecolor($image) && ! imagepalettetotruecolor($image)) {
                return $png;
            }

            $width = imagesx($image);
            $height = imagesy($image);
            if ($width < 16 || $height < 80) {
                return $png;
            }

            // Screenshot images can contain a one-pixel browser/window edge.
            // Ignore the last three rows when locating actual document content.
            $lastInkRow = -1;
            for ($y = $height - 4; $y >= 0; $y--) {
                for ($x = 4; $x < $width - 4; $x += 3) {
                    $pixel = imagecolorat($image, $x, $y);
                    $alpha = ($pixel >> 24) & 0x7f;
                    if ($alpha >= 120) {
                        continue;
                    }
                    $red = ($pixel >> 16) & 0xff;
                    $green = ($pixel >> 8) & 0xff;
                    $blue = $pixel & 0xff;
                    if (min($red, $green, $blue) < 235) {
                        $lastInkRow = $y;
                        break 2;
                    }
                }
            }

            if ($lastInkRow < 0) {
                return $png;
            }

            // Preserve a small white breathing space after the last printed item.
            $croppedHeight = min($height, $lastInkRow + 33);
            if ($croppedHeight >= $height) {
                return $png;
            }

            $cropped = imagecrop($image, [
                'x' => 0,
                'y' => 0,
                'width' => $width,
                'height' => $croppedHeight,
            ]);
            if (! $cropped instanceof \GdImage) {
                return $png;
            }

            try {
                ob_start();
                $encoded = imagepng($cropped);
                $bytes = ob_get_clean();

                return $encoded && is_string($bytes) && str_starts_with($bytes, "\x89PNG\r\n\x1a\n")
                    ? $bytes
                    : $png;
            } finally {
                imagedestroy($cropped);
            }
        } finally {
            imagedestroy($image);
        }
    }
}
