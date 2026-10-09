<?php

use App\Support\Security\SecureUploadValidator;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

it('v2 upload accepts valid PNG with verified detected MIME and extension', function () {
    $file = UploadedFile::fake()->image('barcode.png');

    expect(app(SecureUploadValidator::class)->validate($file))
        ->toBe(['mime' => 'image/png', 'extension' => 'png', 'size' => $file->getSize()]);
});

it('v2 upload refuses SVG content regardless of a plausible filename', function () {
    $file = UploadedFile::fake()->createWithContent('vector.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');

    expect(fn () => app(SecureUploadValidator::class)->validate($file))
        ->toThrow(ValidationException::class);
});

it('v2 upload rejects filename extensions inconsistent with detected MIME', function () {
    $file = UploadedFile::fake()->image('looks-like-pdf.pdf');

    expect(fn () => app(SecureUploadValidator::class)->validate($file))
        ->toThrow(ValidationException::class);
});

it('v2 upload rejects multiple filename extensions even if final extension is allowed', function () {
    $file = UploadedFile::fake()->image('invoice.php.png');

    expect(fn () => app(SecureUploadValidator::class)->validate($file))
        ->toThrow(ValidationException::class);
});

it('v2 upload rejects file sizes beyond the attachment limit', function () {
    $file = UploadedFile::fake()->image('large.png')->size(300);
    config(['attachments.max_size' => 200 * 1024]);

    expect(fn () => app(SecureUploadValidator::class)->validate($file))
        ->toThrow(ValidationException::class);
});

it('v2 upload respects exact MIME allowlist rather than filename alone', function () {
    $file = UploadedFile::fake()->image('image.png');
    config(['attachments.mime_extensions' => ['application/pdf' => ['pdf']]]);

    expect(fn () => app(SecureUploadValidator::class)->validate($file))
        ->toThrow(ValidationException::class);
});