<?php

namespace App\Actions\Finance;

use App\Actions\Periods\EnsurePeriodOpen;
use App\Models\Period\Security;
use App\Support\Audit\AuditContext;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

/** Safe metadata edit: no amount, contact, bank, number or ledger change. */
final class UpdateSecurityDetails
{
    public function __construct(private readonly EnsurePeriodOpen $ensurePeriodOpen) {}

    public function handle(Security $security, string $dueDate, string $notes, int $version, string $key): Security
    {
        MutationAuthorizer::authorize('securities.update');

        return IdempotencyKey::run($key, 'security.update-details:'.$security->id, function () use (
            $security, $dueDate, $notes, $version
        ): Security {
            $date = CarbonImmutable::parse($dueDate)->startOfDay();
            $this->ensurePeriodOpen->handle(now());

            return DB::connection('period')->transaction(function () use ($security, $date, $notes, $version): Security {
                $locked = Security::query()->lockForUpdate()->findOrFail($security->id);
                if (! in_array($locked->status, ['portfolio', 'issued'], true) || $locked->last_payroll_id !== null) {
                    throw new DomainException('İşlem görmüş çek/senet bu ekrandan düzenlenemez.');
                }
                if ($locked->issue_date && $date->lessThan($locked->issue_date)) {
                    throw new DomainException('Vade tarihi evrak düzenleme tarihinden önce olamaz.');
                }
                if (mb_strlen($notes) > 1000) {
                    throw new DomainException('Evrak açıklaması en fazla 1000 karakter olabilir.');
                }

                $oldDueDate = $locked->due_date?->toDateString();
                $updated = $locked->updateWithVersion([
                    'due_date' => $date->toDateString(),
                    'notes' => trim($notes) ?: null,
                ], $version);

                AuditContext::period('Çek/senet belge bilgileri güncellendi.', [
                    'security_id' => $updated->id,
                    'old_due_date' => $oldDueDate,
                    'new_due_date' => $date->toDateString(),
                ], $updated, 'security_details_updated');

                return $updated;
            }, attempts: 3);
        });
    }
}
