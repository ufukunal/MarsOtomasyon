<?php

namespace App\Console\Commands;

use App\Models\Period;
use App\Models\SalesChannelAccount;
use App\Support\Channels\ChannelAdapterResolver;
use App\Support\Reporting\ReportRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Throwable;

class OperationsSmokeCommand extends Command
{
    protected $signature = 'operations:smoke {--external : Aktif kanal hesaplarında read-only connection test çalıştır}';

    protected $description = 'Business veri üretmeden production smoke kontrollerini çalıştırır';

    public function handle(
        ReportRegistry $reports,
        ChannelAdapterResolver $channels,
    ): int {
        $failures = [];

        try {
            DB::connection('master')->select('select 1');
            $this->info('Master DB: OK');
        } catch (Throwable $exception) {
            $failures[] = 'Master DB bağlantısı başarısız: '.$exception->getMessage();
        }

        $original = config('database.connections.period.database');

        try {
            foreach (Period::query()->where('status', 'active')->get() as $period) {
                try {
                    config(['database.connections.period.database' => $period->database_name]);
                    DB::purge('period');
                    DB::connection('period')->select('select 1');
                    $this->line("Period {$period->year}: OK");
                } catch (Throwable $exception) {
                    $failures[] = "Period {$period->year} bağlantısı başarısız: {$exception->getMessage()}";
                }
            }
        } finally {
            config(['database.connections.period.database' => $original]);
            DB::purge('period');
        }

        try {
            $definitions = $reports->definitions();
            if ($definitions === []) {
                $failures[] = 'Rapor registry boş.';
            } else {
                $this->info('Report registry: '.count($definitions).' definition');
            }
        } catch (Throwable $exception) {
            $failures[] = 'Rapor registry çözümlenemedi: '.$exception->getMessage();
        }

        foreach (['login', 'reports.center', 'reports.exports', 'reports.templates'] as $routeName) {
            if (! Route::has($routeName)) {
                $failures[] = "Route eksik: {$routeName}";
            }
        }

        foreach ((array) config('printing.drivers', []) as $key => $driver) {
            if (! is_string($driver) || ! class_exists($driver)) {
                $failures[] = "Print driver çözümlenemedi: {$key}";
            }
        }

        if ($this->option('external')) {
            SalesChannelAccount::query()
                ->where('is_active', true)
                ->orderBy('id')
                ->each(function (SalesChannelAccount $account) use ($channels, &$failures): void {
                    try {
                        $result = $channels->resolve($account)->testConnection($account);
                        if (! $result->success) {
                            $failures[] = "Channel #{$account->id} connection test başarısız.";
                        } else {
                            $this->line("Channel #{$account->id}: OK");
                        }
                    } catch (Throwable $exception) {
                        $failures[] = "Channel #{$account->id} connection test hata: {$exception->getMessage()}";
                    }
                });
        } else {
            $this->comment('External channel connection smoke atlandı; --external ile açıkça çalıştırılabilir.');
        }

        if ($failures !== []) {
            foreach ($failures as $failure) {
                $this->error($failure);
            }

            return self::FAILURE;
        }

        $this->info('Smoke kontrolleri başarılı.');

        return self::SUCCESS;
    }
}
