<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\CompanyDatabaseRegistration;
use App\Models\Period;
use App\Support\Company\CompanyDatabaseName;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use JsonException;

final class Doc2PlanCommand extends Command
{
    protected $signature = 'doc2:plan {--company=* : Yalnız belirtilen Master şirket ID değerleri} {--json : Makinece okunabilir kaynak/hedef envanteri}';

    protected $description = 'DOC2 için Master şirket ve eski dönem veritabanlarını sadece okuyarak taşınma envanteri çıkarır.';

    /** @throws JsonException */
    public function handle(): int
    {
        $requested = array_map('strval', $this->option('company'));
        $ids = [];

        foreach ($requested as $value) {
            if (ctype_digit($value) === false || (int) $value < 1) {
                $this->error('Geçersiz --company değeri.');

                return self::INVALID;
            }

            $ids[] = (int) $value;
        }

        $companies = Company::query()->orderBy('id');
        if ($ids !== []) {
            $companies->whereIn('id', $ids);
        }

        $companies = $companies->get(['id', 'code', 'name', 'is_active']);
        $companyIds = $companies->modelKeys();

        $periods = Period::query()
            ->whereIn('company_id', $companyIds)
            ->orderBy('year')
            ->get(['company_id', 'year', 'database_name', 'status'])
            ->groupBy('company_id');

        $hasRegistry = Schema::connection('master')->hasTable('company_database_registry');
        $registered = $hasRegistry
            ? CompanyDatabaseRegistration::query()->whereIn('company_id', $companyIds)->get()->keyBy('company_id')
            : collect();

        $plan = [];

        foreach ($companies as $company) {
            $id = (int) $company->id;
            $sources = ($periods->get($id) ?? collect())->map(static fn (Period $period): array => [
                'year' => (int) $period->year,
                'database' => $period->database_name,
                'status' => $period->status,
            ])->all();

            $registration = $registered->get($id);
            $plan[] = [
                'company_id' => $id,
                'company_code' => $company->code,
                'company_active' => (bool) $company->is_active,
                'target_database' => CompanyDatabaseName::forCompanyId($id),
                'legacy_period_databases' => $sources,
                'registry_state' => $registration?->state ?? 'unregistered',
                'registry_database_matches' => $registration === null
                    ? null
                    : $registration->database_name === CompanyDatabaseName::forCompanyId($id),
                'requires_data_reconciliation' => count($sources) !== 0,
            ];
        }

        if ($this->option('json')) {
            $this->line(json_encode($plan, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->table(
                ['Şirket ID', 'Şirket kodu', 'Hedef kalıcı DB', 'Kaynak dönem DB', 'Registry'],
                array_map(static fn (array $row): array => [
                    $row['company_id'],
                    $row['company_code'],
                    $row['target_database'],
                    count($row['legacy_period_databases']),
                    $row['registry_state'],
                ], $plan),
            );
            $this->warn('Yalnız metadata okundu. Taşıma, CREATE/DROP DATABASE, migration ve registry aktivasyonu yapılmadı.');
        }

        return self::SUCCESS;
    }
}
