<?php

namespace App\Console\Commands;

use App\Actions\ReferenceData\SeedPeriodReferenceData;
use App\Models\Period;
use App\Support\Period\PeriodContext;
use App\Support\Period\PeriodSchemaVersion;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Throwable;

class MigratePeriodsCommand extends Command
{
    protected $signature = 'migrate:periods
        {--year= : Yalnız belirtilen yıl}
        {--company= : Yalnız belirtilen company_id}
        {--status : Migration durumunu göster, değişiklik yapma}
        {--pretend : SQL üretimini göster, uygulama}
        {--force : Production onayını atla}';

    protected $description = 'Master periods kayıtlarındaki active/closed period DB migrationlarını çalıştırır';

    public function handle(PeriodSchemaVersion $schemaVersion): int
    {
        $oldCompanyId = PeriodContext::companyId();
        $oldPeriodId = PeriodContext::periodId();

        $periods = $this->periodQuery()->get();

        if ($periods->isEmpty()) {
            $this->info('Uygun active/closed dönem bulunamadı.');

            return self::SUCCESS;
        }

        $failed = [];
        $updated = 0;

        try {
            foreach ($periods as $period) {
                $this->newLine();
                $this->info("→ {$period->database_name}");

                try {
                    PeriodContext::useSystem($period->company_id, $period->id);

                    if ($this->option('status')) {
                        $this->renderStatus($period, $schemaVersion);

                        continue;
                    }

                    if ((string) $period->status === 'closed') {
                        DB::connection('period')->statement(
                            'SET default_transaction_read_only = off'
                        );
                    }

                    $arguments = [
                        '--database' => 'period',
                        '--path' => 'database/migrations/period',
                        '--force' => (bool) $this->option('force'),
                    ];

                    if ($this->option('pretend')) {
                        $arguments['--pretend'] = true;
                    }

                    $exitCode = Artisan::call('migrate', $arguments);

                    $output = trim(Artisan::output());

                    if ($output !== '') {
                        $this->line($output);
                    }

                    if ($exitCode !== 0) {
                        throw new \RuntimeException("migrate exit code={$exitCode}");
                    }

                    if ($this->option('pretend')) {
                        $this->comment('Pretend: schema_version değiştirilmedi.');

                        continue;
                    }

                    app(SeedPeriodReferenceData::class)->handle();

                    $version = $schemaVersion->currentDatabaseVersion();

                    Period::query()
                        ->whereKey($period->id)
                        ->update(['schema_version' => $version]);

                    $updated++;

                    $this->info("  schema_version={$version}");
                } catch (Throwable $exception) {
                    $failed[] = [
                        'period_id' => $period->id,
                        'database' => $period->database_name,
                        'error' => $exception->getMessage(),
                    ];

                    $this->error("  HATA: {$exception->getMessage()}");
                }
            }
        } finally {
            PeriodContext::clear();

            if ($oldCompanyId && $oldPeriodId) {
                PeriodContext::useSystem($oldCompanyId, $oldPeriodId);
            }
        }

        $this->newLine();

        if ($this->option('status')) {
            $this->info(sprintf(
                'Durum kontrolü tamamlandı: %d dönem, %d hata.',
                $periods->count(),
                count($failed),
            ));
        } elseif ($this->option('pretend')) {
            $this->info(sprintf(
                'Pretend tamamlandı: %d dönem, %d hata.',
                $periods->count(),
                count($failed),
            ));
        } else {
            $this->info(sprintf(
                'Migration tamamlandı: %d güncellendi, %d hata.',
                $updated,
                count($failed),
            ));
        }

        return $failed === [] ? self::SUCCESS : self::FAILURE;
    }

    /** @return Builder<Period> */
    private function periodQuery(): Builder
    {
        return Period::query()
            ->whereIn('status', ['active', 'closed'])
            ->when(
                $this->option('year'),
                fn (Builder $query, $year): Builder => $query->where('year', (int) $year),
            )
            ->when(
                $this->option('company'),
                fn (Builder $query, $company): Builder => $query->where('company_id', (int) $company),
            )
            ->orderBy('company_id')
            ->orderBy('year');
    }

    private function renderStatus(Period $period, PeriodSchemaVersion $schemaVersion): void
    {
        $status = $schemaVersion->status();

        $this->line('  Master schema_version: '.($period->schema_version ?? '—'));
        $this->line('  DB current: '.($status['current'] ?? '—'));
        $this->line('  Target: '.($status['expected'] ?? '—'));
        $this->line('  Pending: '.count($status['pending']));

        foreach ($status['pending'] as $migration) {
            $this->line("    - {$migration}");
        }

        if ($status['extra'] !== []) {
            $this->warn('  Repo dışında DB migration kaydı var:');

            foreach ($status['extra'] as $migration) {
                $this->line("    - {$migration}");
            }
        }
    }
}
