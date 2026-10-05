<?php

namespace App\Console\Commands;

use App\Models\BackupRun;
use App\Support\Operations\OperationalHealthService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class OperationsGoLivePreflightCommand extends Command
{
    protected $signature = 'operations:go-live-preflight
        {--external : Aktif kanal hesaplarında read-only connection smoke çalıştır}
        {--max-backup-age=36 : Verified recovery set için maksimum yaş (saat)}';

    protected $description = 'Go-live öncesi recovery/security/integrity/smoke/health green gate kontrollerini fail-fast çalıştırır';

    public function handle(OperationalHealthService $health): int
    {
        $maxAge = max(1, (int) $this->option('max-backup-age'));

        $backup = BackupRun::query()
            ->where('status', 'verified')
            ->whereNotNull('verified_at')
            ->whereNotNull('finished_at')
            ->latest('finished_at')
            ->first();

        if (! $backup) {
            $this->error('Go-live bloklandı: verified recovery set bulunamadı.');

            return self::FAILURE;
        }

        $ageHours = $backup->finished_at->diffInHours(now());

        if ($ageHours > $maxAge) {
            $this->error(
                "Go-live bloklandı: verified recovery set stale ({$ageHours} saat > {$maxAge} saat)."
            );

            return self::FAILURE;
        }

        $this->info(
            'Recovery set: OK #'.$backup->id.' '.$backup->recovery_set_id.
            ' (age='.$ageHours.'h)'
        );

        if (Artisan::call('operations:security-check') !== 0) {
            $this->error('Go-live bloklandı: production security gate başarısız.');

            return self::FAILURE;
        }
        $this->info('Security: OK');

        if (Artisan::call('integrity:all', ['--include-closed' => true]) !== 0) {
            $this->error('Go-live bloklandı: integrity gate başarısız.');

            return self::FAILURE;
        }
        $this->info('Integrity: OK');

        $smokeArgs = $this->option('external') ? ['--external' => true] : [];

        if (Artisan::call('operations:smoke', $smokeArgs) !== 0) {
            $this->error('Go-live bloklandı: smoke gate başarısız.');

            return self::FAILURE;
        }
        $this->info('Smoke: OK');

        $result = $health->check(true);

        if ($result['status'] !== 'healthy') {
            $this->error(
                'Go-live bloklandı: operational health '.$result['status'].
                '. correlation_id='.$result['correlation_id']
            );

            return self::FAILURE;
        }

        $this->info('Health: OK correlation_id='.$result['correlation_id']);
        $this->info('GO-LIVE PREFLIGHT GREEN. Trafik açma kararı runbook sırasına göre uygulanabilir.');

        return self::SUCCESS;
    }
}
