<?php

use App\Jobs\ProcessCardImport;
use App\Livewire\Pages\Import\ImportWizard;
use App\Models\Period\CardImportBatch;
use App\Models\Period\CardImportError;
use App\Models\Period\Product;
use App\Support\Import\ImportFileReader;
use App\Support\Import\ImportRowImporterResolver;
use App\Support\Period\PeriodContext;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function productImportMapping(): array
{
    return [
        'code' => 'code',
        'name' => 'name',
        'unit_code' => 'unit_code',
        'barcode' => null,
        'vat_rate' => null,
        'list_price' => null,
        'currency' => null,
        'kind' => null,
        'channel_stock_mode' => null,
    ];
}

it('1000 satırlık ürün CSV importunu eksiksiz tamamlar', function () {
    Storage::fake('imports');

    [$company, $period] = $this->createCompanyWithPeriod('IMP1000');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);

    $rows = ["code;name;unit_code"];

    for ($i = 1; $i <= 1000; $i++) {
        $rows[] = sprintf('IMP-%04d;Ürün %04d;ADET', $i, $i);
    }

    $content = implode("\n", $rows)."\n";
    Storage::disk('imports')->put('bulk.csv', $content);

    $batch = CardImportBatch::query()->create([
        'type' => 'product',
        'source_disk' => 'imports',
        'source_path' => 'bulk.csv',
        'original_name' => 'bulk.csv',
        'file_hash' => hash('sha256', $content),
        'mapping' => productImportMapping(),
        'error_mode' => 'cancel_all',
        'status' => 'pending',
        'created_by' => $admin->id,
        'created_by_name' => $admin->name,
    ]);

    $job = new ProcessCardImport($company->id, $period->id, $batch->id);
    $job->handle(app(ImportFileReader::class), app(ImportRowImporterResolver::class));

    PeriodContext::use($company->id, $period->id);

    expect($batch->refresh()->status)->toBe('done')
        ->and($batch->success_rows)->toBe(1000)
        ->and($batch->error_rows)->toBe(0)
        ->and(Product::query()->where('code', 'like', 'IMP-%')->count())->toBe(1000);
});

it('hatalı satırı raporlar ve skip_invalid modunda geçerli satırı uygular', function () {
    Storage::fake('imports');

    [$company, $period] = $this->createCompanyWithPeriod('IMPERR');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);

    $content = "code;name;unit_code\nOK-1;Geçerli;ADET\nBAD-1;;ADET\n";
    Storage::disk('imports')->put('errors.csv', $content);

    $batch = CardImportBatch::query()->create([
        'type' => 'product',
        'source_disk' => 'imports',
        'source_path' => 'errors.csv',
        'original_name' => 'errors.csv',
        'file_hash' => hash('sha256', $content),
        'mapping' => productImportMapping(),
        'error_mode' => 'skip_invalid',
        'status' => 'pending',
        'created_by' => $admin->id,
        'created_by_name' => $admin->name,
    ]);

    (new ProcessCardImport($company->id, $period->id, $batch->id))
        ->handle(app(ImportFileReader::class), app(ImportRowImporterResolver::class));

    PeriodContext::use($company->id, $period->id);
    $batch->refresh();

    expect($batch->status)->toBe('done')
        ->and($batch->success_rows)->toBe(1)
        ->and($batch->error_rows)->toBe(1)
        ->and(CardImportError::query()->where('batch_id', $batch->id)->count())->toBeGreaterThan(0)
        ->and(Product::query()->where('code', 'OK-1')->exists())->toBeTrue()
        ->and(Product::query()->where('code', 'BAD-1')->exists())->toBeFalse();
});

it('aynı dosya hash ve tip ikinci kez kuyruğa alınırken yeni batch üretmez', function () {
    [$company, $period] = $this->createCompanyWithPeriod('IMPDUP');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);

    $hash = hash('sha256', 'same-file');

    $batch = CardImportBatch::query()->create([
        'type' => 'product',
        'source_disk' => 'imports',
        'source_path' => 'done.csv',
        'original_name' => 'done.csv',
        'file_hash' => $hash,
        'mapping' => productImportMapping(),
        'error_mode' => 'cancel_all',
        'status' => 'done',
        'created_by' => $admin->id,
        'created_by_name' => $admin->name,
    ]);

    Livewire::test(ImportWizard::class)
        ->set('type', 'product')
        ->set('fileHash', $hash)
        ->call('queue')
        ->assertSet('batchId', $batch->id);

    expect(CardImportBatch::query()->where('file_hash', $hash)->count())->toBe(1);
});
