<?php

use App\Support\Security\SecureUploadValidator;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

it('rejects unsafe extensions even when their mime is whitelisted', function (): void {
    $upload = UploadedFile::fake()->createWithContent('malware.php', 'plain text');
    expect(fn () => (new SecureUploadValidator)->validate($upload))->toThrow(ValidationException::class);
});

it('rejects double extension filenames and oversized files', function (): void {
    $validator = new SecureUploadValidator;
    $double = UploadedFile::fake()->createWithContent('document.backup.csv', 'sku,qty');
    expect(fn () => $validator->validate($double))->toThrow(ValidationException::class);
    config(['attachments.max_size' => 1]);
    $large = UploadedFile::fake()->createWithContent('input.csv', 'some long csv content');
    expect(fn () => $validator->validate($large))->toThrow(ValidationException::class);
});
