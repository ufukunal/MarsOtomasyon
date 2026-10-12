<?php

namespace App\Support\Printing\Label;

use Illuminate\Support\HtmlString;
use InvalidArgumentException;

/**
 * Fixed version-3 QR encoder, error correction M, for opaque 32-hex tokens.
 * The generated SVG contains only the encoded token (no shipping data or text).
 */
final class OpaqueQrSvg
{
    private const SIZE = 29;

    public static function token(string $scope, int $ordinal, string $key): string
    {
        if ($scope === '' || $ordinal < 0 || $key === '') {
            throw new InvalidArgumentException('QR scope, parcel ordinal and signing key are required.');
        }

        return strtoupper(substr(hash_hmac('sha256', "mars-label-v1|{$scope}|{$ordinal}", $key), 0, 32));
    }

    public static function render(string $token): HtmlString
    {
        if (preg_match('/\A[A-F0-9]{32}\z/D', $token) !== 1) {
            throw new InvalidArgumentException('A 32-character hexadecimal token is required.');
        }

        $matrix = self::matrix($token);
        $path = [];
        for ($y = 0; $y < self::SIZE; $y++) {
            for ($x = 0; $x < self::SIZE; $x++) {
                if ($matrix[$y][$x]) {
                    $path[] = 'M'.($x + 4).' '.($y + 4).'h1v1h-1z';
                }
            }
        }

        return new HtmlString(
            '<svg xmlns="http://www.w3.org/2000/svg" class="label-qr" viewBox="0 0 37 37"'
            .' shape-rendering="crispEdges" role="img" aria-label="Sevkiyat QR kodu">'
            .'<path fill="#111" d="'.implode('', $path).'"/></svg>'
        );
    }

    /** @return list<list<bool>> */
    private static function matrix(string $token): array
    {
        $size = self::SIZE;
        $modules = array_fill(0, $size, array_fill(0, $size, false));
        $reserved = array_fill(0, $size, array_fill(0, $size, false));
        $set = static function (int $x, int $y, bool $dark) use (&$modules, &$reserved, $size): void {
            if ($x >= 0 && $y >= 0 && $x < $size && $y < $size) {
                $modules[$y][$x] = $dark;
                $reserved[$y][$x] = true;
            }
        };

        foreach ([[3, 3], [$size - 4, 3], [3, $size - 4]] as [$cx, $cy]) {
            for ($dy = -4; $dy <= 4; $dy++) {
                for ($dx = -4; $dx <= 4; $dx++) {
                    $radius = max(abs($dx), abs($dy));
                    $set($cx + $dx, $cy + $dy, $radius !== 2 && $radius !== 4);
                }
            }
        }
        for ($i = 8; $i < $size - 8; $i++) {
            $set(6, $i, $i % 2 === 0);
            $set($i, 6, $i % 2 === 0);
        }
        for ($dy = -2; $dy <= 2; $dy++) {
            for ($dx = -2; $dx <= 2; $dx++) {
                $set(22 + $dx, 22 + $dy, max(abs($dx), abs($dy)) !== 1);
            }
        }

        // BCH-protected QR format bits for ECC M, mask 0.
        $format = 0x5412;
        for ($i = 0; $i <= 5; $i++) {
            $set(8, $i, (bool) (($format >> $i) & 1));
        }
        $set(8, 7, (bool) (($format >> 6) & 1));
        $set(8, 8, (bool) (($format >> 7) & 1));
        $set(7, 8, (bool) (($format >> 8) & 1));
        for ($i = 9; $i < 15; $i++) {
            $set(14 - $i, 8, (bool) (($format >> $i) & 1));
        }
        for ($i = 0; $i < 8; $i++) {
            $set($size - 1 - $i, 8, (bool) (($format >> $i) & 1));
        }
        for ($i = 8; $i < 15; $i++) {
            $set(8, $size - 15 + $i, (bool) (($format >> $i) & 1));
        }
        $set(8, $size - 8, true);

        $alphabet = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ $%*+-./:';
        $bits = '0010'.str_pad(decbin(strlen($token)), 9, '0', STR_PAD_LEFT);
        foreach (str_split($token, 2) as $pair) {
            $v = (int) strpos($alphabet, $pair[0]) * 45 + (int) strpos($alphabet, $pair[1]);
            $bits .= str_pad(decbin($v), 11, '0', STR_PAD_LEFT);
        }
        $bits .= str_repeat('0', min(4, 352 - strlen($bits)));
        $bits .= str_repeat('0', (8 - strlen($bits) % 8) % 8);
        $data = [];
        foreach (str_split($bits, 8) as $octet) {
            $data[] = bindec($octet);
        }
        for ($i = 0; count($data) < 44; $i++) {
            $data[] = $i % 2 === 0 ? 0xEC : 0x11;
        }

        $codewords = [...$data, ...self::remainder($data)];
        $bitIndex = 0;
        for ($right = $size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right = 5;
            }
            for ($vert = 0; $vert < $size; $vert++) {
                $y = (($right + 1) & 2) === 0 ? $size - 1 - $vert : $vert;
                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;
                    if ($reserved[$y][$x]) {
                        continue;
                    }
                    $dark = $bitIndex < count($codewords) * 8
                        && (($codewords[intdiv($bitIndex, 8)] >> (7 - $bitIndex % 8)) & 1) !== 0;
                    $modules[$y][$x] = $dark !== (($x + $y) % 2 === 0);
                    $bitIndex++;
                }
            }
        }

        return $modules;
    }

    /**
     * @param  list<int>  $data
     * @return list<int>
     */
    private static function remainder(array $data): array
    {
        $degree = 26;
        $divisor = array_fill(0, $degree, 0);
        $divisor[$degree - 1] = 1;
        $root = 1;
        for ($i = 0; $i < $degree; $i++) {
            for ($j = 0; $j < $degree; $j++) {
                $divisor[$j] = self::multiply($divisor[$j], $root);
                if ($j + 1 < $degree) {
                    $divisor[$j] ^= $divisor[$j + 1];
                }
            }
            $root = self::multiply($root, 0x02);
        }
        $rem = array_fill(0, $degree, 0);
        foreach ($data as $byte) {
            $factor = $byte ^ $rem[0];
            array_shift($rem);
            $rem[] = 0;
            for ($i = 0; $i < $degree; $i++) {
                $rem[$i] ^= self::multiply($divisor[$i], $factor);
            }
        }

        return $rem;
    }

    private static function multiply(int $x, int $y): int
    {
        $z = 0;
        for ($i = 7; $i >= 0; $i--) {
            $z = ($z << 1) ^ (($z >> 7) * 0x11D);
            $z ^= (($y >> $i) & 1) * $x;
        }

        return $z;
    }
}
