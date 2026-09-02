<?php

use App\Modules\Core\Models\Company;
use App\Modules\Reports\Bi\BiDataset;
use App\Modules\Reports\Bi\BiDatasetRegistry;
use App\Modules\Reports\Bi\BiExportService;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;

uses(DatabaseMigrations::class);

it('exports only allow-listed company-scoped fields with schema version and watermark evidence', function (): void {
    $company = Company::query()->create(['code' => 'M31', 'name' => 'M31 Company']);
    $dataset = new M31FixtureDataset((int) $company->getKey());
    $registry = new BiDatasetRegistry;
    $registry->register($dataset);
    $service = new BiExportService($registry);

    $result = $service->export((int) $company->getKey(), 'sales_fact', 'json', ['document_no', 'net_total'], '100');

    expect($result['schema_version'])->toBe(2)
        ->and($result['row_count'])->toBe(1)
        ->and($result['watermark'])->toBe('101')
        ->and(json_decode($result['content'], true, flags: JSON_THROW_ON_ERROR))->toBe([['document_no' => 'INV-31', 'net_total' => '100.00']])
        ->and((string) DB::table('bi_export_runs')->where('id', $result['run_id'])->value('status'))->toBe('succeeded')
        ->and((string) DB::table('bi_export_runs')->where('id', $result['run_id'])->value('artifact_sha256'))->toBe($result['sha256']);

    expect(fn () => $service->export((int) $company->getKey(), 'sales_fact', 'json', ['customer_email']))
        ->toThrow(DomainException::class, 'explicit authorization');

    $dataset->emitForeignCompany = true;
    expect(fn () => $service->export((int) $company->getKey(), 'sales_fact', 'csv', ['document_no']))
        ->toThrow(DomainException::class, 'cross-company');
    expect((string) DB::table('bi_export_runs')->orderByDesc('id')->value('status'))->toBe('failed');
});

final class M31FixtureDataset implements BiDataset
{
    public bool $emitForeignCompany = false;

    public function __construct(private readonly int $companyId) {}

    public function key(): string
    {
        return 'sales_fact';
    }

    public function schemaVersion(): int
    {
        return 2;
    }

    public function fields(): array
    {
        return [
            'document_no' => ['pii' => false],
            'net_total' => ['pii' => false],
            'customer_email' => ['pii' => true],
        ];
    }

    public function rows(int $companyId, ?string $watermark = null): iterable
    {
        yield [
            'company_id' => $this->emitForeignCompany ? $this->companyId + 1 : $companyId,
            'document_no' => 'INV-31',
            'net_total' => '100.00',
            'customer_email' => 'customer@example.test',
        ];
    }

    public function nextWatermark(): ?string
    {
        return '101';
    }
}
