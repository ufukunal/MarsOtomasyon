<?php

use App\Modules\Core\Models\Company;
use App\Modules\Reports\Bi\BiDataset;
use App\Modules\Reports\Bi\BiDatasetRegistry;
use App\Modules\Reports\Bi\BiExportService;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;

uses(DatabaseMigrations::class);

it('exports allow-listed company rows with masking, watermark and artifact evidence', function (): void {
    $company = Company::query()->create(['code' => 'M31', 'name' => 'M31 Company']);
    $dataset = new class((int) $company->getKey()) implements BiDataset
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
                'customer_email' => ['pii' => true],
            ];
        }

        public function rows(int $companyId, ?string $watermark = null): iterable
        {
            yield [
                'company_id' => $this->emitForeignCompany ? $this->companyId + 1 : $companyId,
                'document_no' => 'INV-31',
                'customer_email' => 'customer@example.test',
            ];
        }

        public function nextWatermark(): ?string
        {
            return '101';
        }
    };
    $registry = (new BiDatasetRegistry)->register($dataset);
    $service = new BiExportService($registry);

    $masked = $service->export(
        companyId: (int) $company->getKey(),
        datasetKey: 'sales_fact',
        format: 'json',
        requestedFields: ['document_no', 'customer_email'],
        watermark: '100',
    );

    expect($masked['schema_version'])->toBe(2)
        ->and($masked['row_count'])->toBe(1)
        ->and($masked['watermark'])->toBe('101')
        ->and($masked['status'])->toBe('succeeded')
        ->and(json_decode($masked['content'], true, flags: JSON_THROW_ON_ERROR))->toBe([
            ['document_no' => 'INV-31', 'customer_email' => '[MASKED]'],
        ])
        ->and((string) DB::table('bi_export_runs')->where('id', $masked['run_id'])->value('artifact_sha256'))->toBe($masked['sha256'])
        ->and(DB::table('bi_export_runs')->where('id', $masked['run_id'])->value('artifact_expires_at'))->not->toBeNull();

    $pii = $service->export(
        companyId: (int) $company->getKey(),
        datasetKey: 'sales_fact',
        format: 'csv',
        requestedFields: ['customer_email'],
        includePii: true,
    );
    expect($pii['content'])->toContain('customer@example.test');

    expect(fn () => $service->export((int) $company->getKey(), 'sales_fact', 'json', ['not_allowed']))
        ->toThrow(DomainException::class, 'not allow-listed');

    $dataset->emitForeignCompany = true;
    expect(fn () => $service->export((int) $company->getKey(), 'sales_fact', 'json', ['document_no']))
        ->toThrow(DomainException::class, 'cross-company');
    expect((string) DB::table('bi_export_runs')->orderByDesc('id')->value('status'))->toBe('failed');
});
