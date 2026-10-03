<?php

use App\Actions\Attachments\StoreAttachment;
use App\Models\Attachment;
use App\Models\Period\Product;
use App\Models\Period\Unit;
use App\Support\Integrity\Checks\FilesIntegrityCheck;
use App\Support\Period\PeriodContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

it('izinli eki saklar ve attachment silinince fiziksel dosyayı kaldırır', function () {
    Storage::fake('attachments');

    [$company, $period] = $this->createCompanyWithPeriod('FILE');
    PeriodContext::useSystem($company->id, $period->id);

    $unit = Unit::query()->where('code', 'ADET')->firstOrFail();
    $product = Product::query()->create([
        'code' => 'P-FILE',
        'name' => 'Dosyalı Ürün',
        'unit_id' => $unit->id,
        'vat_rate' => '20',
        'list_price' => '10',
        'currency' => 'TRY',
        'kind' => 'normal',
        'allow_negative_stock' => false,
        'min_stock' => '0',
        'channel_stock_mode' => 'stock',
        'is_active' => true,
    ]);

    $attachment = app(StoreAttachment::class)->handle(
        $product,
        UploadedFile::fake()->image('urun.jpg'),
    );

    Storage::disk('attachments')->assertExists($attachment->path);

    $path = $attachment->path;
    $attachment->delete();

    Storage::disk('attachments')->assertMissing($path);
});

it('izin verilmeyen dosya türünü reddeder', function () {
    Storage::fake('attachments');

    [$company, $period] = $this->createCompanyWithPeriod('BADFILE');
    PeriodContext::useSystem($company->id, $period->id);

    $unit = Unit::query()->where('code', 'ADET')->firstOrFail();
    $product = Product::query()->create([
        'code' => 'P-BAD',
        'name' => 'Ürün',
        'unit_id' => $unit->id,
        'vat_rate' => '20',
        'list_price' => '10',
        'currency' => 'TRY',
        'kind' => 'normal',
        'allow_negative_stock' => false,
        'min_stock' => '0',
        'channel_stock_mode' => 'stock',
        'is_active' => true,
    ]);

    expect(fn () => app(StoreAttachment::class)->handle(
        $product,
        UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload'),
    ))->toThrow(ValidationException::class);
});

it('integrity files eksik fiziksel dosyayı raporlar ve kaydı değiştirmez', function () {
    Storage::fake('attachments');

    [$company, $period] = $this->createCompanyWithPeriod('INTFILE');
    PeriodContext::useSystem($company->id, $period->id);

    $attachment = Attachment::query()->forceCreate([
        'attachable_type' => Product::class,
        'attachable_id' => 999,
        'disk' => 'attachments',
        'path' => 'missing/not-there.pdf',
        'original_name' => 'missing.pdf',
        'mime' => 'application/pdf',
        'size' => 100,
    ]);

    $result = app(FilesIntegrityCheck::class)->run();

    expect($result->mismatchCount())->toBe(1)
        ->and($result->mismatches[0]['attachment_id'])->toBe($attachment->id)
        ->and(Attachment::query()->whereKey($attachment->id)->exists())->toBeTrue();
});
