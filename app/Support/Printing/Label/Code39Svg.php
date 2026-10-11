<?php

namespace App\Support\Printing\Label;

use Illuminate\Support\HtmlString;

final class Code39Svg
{
    /** @var array<string, string> */
    private const PATTERNS = [
        '0' => 'nnnwwnwnn', '1' => 'wnnwnnnnw', '2' => 'nnwwnnnnw',
        '3' => 'wnwwnnnnn', '4' => 'nnnwwnnnw', '5' => 'wnnwwnnnn',
        '6' => 'nnwwwnnnn', '7' => 'nnnwnnwnw', '8' => 'wnnwnnwnn',
        '9' => 'nnwwnnwnn', 'A' => 'wnnnnwnnw', 'B' => 'nnwnnwnnw',
        'C' => 'wnwnnwnnn', 'D' => 'nnnnwwnnw', 'E' => 'wnnnwwnnn',
        'F' => 'nnwnwwnnn', 'G' => 'nnnnnwwnw', 'H' => 'wnnnnnwwn',
        'I' => 'nnwnnwwnn', 'J' => 'nnnnwwwnn', 'K' => 'wnnnnnnww',
        'L' => 'nnwnnnnww', 'M' => 'wnwnnnnwn', 'N' => 'nnnnwnnww',
        'O' => 'wnnnwnnwn', 'P' => 'nnwnwnnwn', 'Q' => 'nnnnnnwww',
        'R' => 'wnnnnnwwn', 'S' => 'nnwnnnwwn', 'T' => 'nnnnwnwwn',
        'U' => 'wwnnnnnnw', 'V' => 'nwwnnnnnw', 'W' => 'wwwnnnnnn',
        'X' => 'nwnnwnnnw', 'Y' => 'wwnnwnnnn', 'Z' => 'nwwnwnnnn',
        '-' => 'nwnnnnwnw', '.' => 'wwnnnnwnn', ' ' => 'nwwnnnwnn',
        '*' => 'nwnnwnwnn',
    ];

    public static function render(string $value): HtmlString
    {
        $value = strtoupper($value);
        // Printed identification is deliberately not silently altered.
        if (! preg_match('/^[A-Z0-9. \-]+$/', $value)) {
            return new HtmlString('<span>Geçersiz barkod değeri</span>');
        }

        $x = 0;
        $bars = '';

        foreach (str_split('*'.$value.'*') as $character) {
            $pattern = self::PATTERNS[$character];

            foreach (str_split($pattern) as $index => $width) {
                $pixels = $width === 'w' ? 3 : 1;

                if ($index % 2 === 0) {
                    $bars .= '<rect x="'.$x.'" y="0" width="'.$pixels.'" height="42"/>';
                }

                $x += $pixels;
            }

            $x++;
        }

        $safe = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return new HtmlString(
            '<svg xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Barkod: '.$safe
            .'" viewBox="0 0 '.$x.' 60" preserveAspectRatio="xMidYMid meet"'
            .' style="display:block;width:100%;max-width:400px;height:57px;margin:4px auto" fill="#111">'
            .$bars.'<text x="'.($x / 2).'" y="56" font-size="9" text-anchor="middle">'
            .$safe.'</text></svg>'
        );
    }
}
