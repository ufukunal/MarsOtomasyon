<?php

namespace App\Support\Products;

use RuntimeException;

/**
 * Creates small, deterministic product-specific PNG illustrations for UAT only.
 * No third-party images, network requests or external binaries are required.
 */
final class UatDemoImagePainter
{
    public static function png(int $variant, bool $alternate = false): string
    {
        if (! extension_loaded('gd')) {
            throw new RuntimeException('UAT demo resimleri için GD uzantısı gerekli.');
        }

        $w = 520;
        $h = 390;
        $img = imagecreatetruecolor($w, $h);
        if ($img === false) {
            throw new RuntimeException('UAT demo görseli oluşturulamadı.');
        }

        $background = imagecolorallocate($img, 244, 247, 251);
        $white = imagecolorallocate($img, 255, 255, 255);
        $gray = imagecolorallocate($img, 116, 130, 150);
        $dark = imagecolorallocate($img, 39, 53, 73);
        $metal = imagecolorallocate($img, 182, 126, 32);
        $warm = imagecolorallocate($img, 255, 188, 62);
        $accent = imagecolorallocate($img, 40, 106, 190);
        $shadow = imagecolorallocate($img, 215, 225, 238);

        imagefilledrectangle($img, 0, 0, $w, $h, $background);
        imagefilledrectangle($img, 24, 24, $w - 24, $h - 24, $white);
        imagefilledellipse($img, 260, 336, 260, 25, $shadow);

        $shift = $alternate ? 16 : 0;
        switch ($variant) {
            case 1: // Gold chandelier: canopy, arms and seven bulbs.
            case 2: // Crystal chandelier: cooler finish and pendants.
                $finish = $variant === 1 ? $metal : $gray;
                imagesetthickness($img, 9);
                imageline($img, 260, 48, 260, 182, $finish);
                imagefilledrectangle($img, 222, 48, 298, 62, $finish);
                imagefilledellipse($img, 260, 180, 60, 25, $finish);
                foreach ([-145, -100, -52, 0, 52, 100, 145] as $dx) {
                    $x = 260 + $dx;
                    imageline($img, 260, 182, $x, 226 - (int) (abs($dx) / 10), $finish);
                    imagefilledellipse($img, $x, 228 - (int) (abs($dx) / 10), 40, 49, $variant === 1 ? $warm : $accent);
                    if ($variant === 2) {
                        imagefilledellipse($img, $x, 262 - (int) (abs($dx) / 10), 18, 37, $gray);
                    }
                }
                break;
            case 3: // Pendant lamp.
                imagesetthickness($img, 8);
                imageline($img, 260, 48, 260 + $shift, 205, $dark);
                imagefilledrectangle($img, 222, 48, 298, 60, $dark);
                imagefilledarc($img, 260 + $shift, 222, 245, 174, 0, 180, $accent, IMG_ARC_PIE);
                imagefilledellipse($img, 260 + $shift, 289, 202, 12, $dark);
                imagefilledellipse($img, 260 + $shift, 295, 90, 18, $warm);
                break;
            case 4: // LED driver / power module.
                imagefilledrectangle($img, 107, 120, 413, 273, $shadow);
                imagefilledrectangle($img, 119, 130, 401, 262, $dark);
                imagefilledrectangle($img, 142, 153, 381, 229, $white);
                imagefilledrectangle($img, 157, 170, 360, 180, $accent);
                imagefilledrectangle($img, 157, 192, 305, 201, $gray);
                imagefilledrectangle($img, 157, 210, 332, 219, $gray);
                foreach ([145, 165, 185, 205, 225, 245] as $y) {
                    imagefilledrectangle($img, 100, $y, 114, $y + 9, $metal);
                    imagefilledrectangle($img, 406, $y, 421, $y + 9, $metal);
                }
                break;
            case 5: // Wall sconce: bracket and illuminated shade.
                imagefilledrectangle($img, 146, 122, 171, 282, $metal);
                imagefilledellipse($img, 158, 201, 64, 96, $metal);
                imagesetthickness($img, 10);
                imageline($img, 174, 201, 274, 201, $metal);
                imagefilledarc($img, 332, 217, 205, 192, 180, 360, $warm, IMG_ARC_PIE);
                imagefilledellipse($img, 332, 216, 204, 18, $metal);
                break;
            default: // Crystal connector / fastening hardware.
                imagefilledellipse($img, 260, 206, 197, 197, $metal);
                imagefilledellipse($img, 260, 206, 140, 140, $shadow);
                imagefilledellipse($img, 260, 206, 86, 86, $dark);
                imagesetthickness($img, 9);
                imageline($img, 141, 120, 379, 295, $gray);
                imageline($img, 379, 120, 141, 295, $gray);
                imagefilledellipse($img, 260, 206, 65, 65, $accent);
                break;
        }

        // Distinct alternate view marker, keeping the same product silhouette.
        if ($alternate) {
            imagefilledrectangle($img, 395, 45, 468, 51, $accent);
            imagefilledrectangle($img, 415, 59, 468, 63, $gray);
        }

        // Enlarge the illustrated object within the same output dimensions.
        // Crop only the empty margins, then upscale once to preserve the PNG API.
        $framed = imagecreatetruecolor($w, $h);
        if ($framed === false) {
            imagedestroy($img);
            throw new RuntimeException('UAT demo görseli yeniden boyutlandırılamadı.');
        }
        imagecopyresampled($framed, $img, 0, 0, 63, 32, $w, $h, 394, 310);
        imagedestroy($img);
        $img = $framed;

        ob_start();
        $ok = imagepng($img, null, 8);
        $bytes = ob_get_clean();
        imagedestroy($img);

        if (! $ok || ! is_string($bytes) || $bytes === '') {
            throw new RuntimeException('UAT demo PNG kodlanamadı.');
        }

        return $bytes;
    }
}
