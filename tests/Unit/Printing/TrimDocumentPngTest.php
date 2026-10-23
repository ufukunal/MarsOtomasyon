<?php

use App\Support\Printing\TrimDocumentPng;

it('removes bottom whitespace from a PNG without losing its content or width', function (): void {
    $image = imagecreatetruecolor(240, 720);
    $white = imagecolorallocate($image, 255, 255, 255);
    $black = imagecolorallocate($image, 15, 15, 15);
    $border = imagecolorallocate($image, 221, 226, 231);
    imagefilledrectangle($image, 0, 0, 239, 719, $white);
    imagefilledrectangle($image, 16, 15, 225, 180, $black);
    // A screenshot can contain a one-pixel artifact at the very bottom.
    imageline($image, 0, 719, 239, 719, $border);

    ob_start();
    imagepng($image);
    $png = ob_get_clean();
    imagedestroy($image);

    expect($png)->toBeString();
    $cropped = app(TrimDocumentPng::class)->handle($png);
    $dimensions = getimagesizefromstring($cropped);

    expect($cropped)->toStartWith("\x89PNG\r\n\x1a\n")
        ->and($dimensions[0])->toBe(240)
        ->and($dimensions[1])->toBe(213);
});

it('leaves empty or unrecognizable PNG data unchanged', function (): void {
    expect(app(TrimDocumentPng::class)->handle('not an image'))->toBe('not an image');

    $image = imagecreatetruecolor(100, 300);
    $white = imagecolorallocate($image, 255, 255, 255);
    imagefill($image, 0, 0, $white);
    ob_start();
    imagepng($image);
    $png = ob_get_clean();
    imagedestroy($image);

    expect(app(TrimDocumentPng::class)->handle($png))->toBe($png);
});

it('applies blank-area cropping only to PNG and preserves A4 PDF HTML', function (): void {
    $controller = file_get_contents(base_path('app/Http/Controllers/PrintBusinessDocumentController.php'));
    $slip = file_get_contents(base_path('resources/views/printing/business-document-slip.blade.php'));

    preg_match('/if \(\$format === \'png\'\) \{(.*?)\/\/ Print the same fully rendered/s', $controller, $match);
    $pngHandler = $match[1] ?? '';

    expect($pngHandler)->toContain("file_put_contents(\$htmlFile, \$captureHtml)")
        ->toContain("app(TrimDocumentPng::class)->handle(\$image)")
        ->toContain('background: #fff !important')
        ->toContain('min-height: 0 !important')
        ->and($slip)->toContain('@page { size: A4 portrait;')
        ->toContain('min-height: 297mm');
});
