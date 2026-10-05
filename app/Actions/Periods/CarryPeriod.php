<?php

namespace App\Actions\Periods;

use App\Actions\Channels\CarryChannelPeriodState;
use App\Actions\Imports\CarryImportFileFromPeriod;
use App\DataObjects\Periods\CarryResult;
use App\Models\Period;
use App\Support\Audit\AuditContext;
use App\Support\Concurrency\IdempotencyKey;
use App\Support\Integrity\Checks\CarryIntegrityCheck;
use App\Support\Integrity\IntegrityRunner;
use App\Support\Period\PeriodContext;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class CarryPeriod
{
    public function __construct(
        private readonly PreviewPeriodCarry $preview,
        private readonly CreatePeriod $createPeriod,
        private readonly CopyPeriodCards $cards,
        private readonly CopyPeriodOpenings $openings,
        private readonly CarryOpenOrders $orders,
        private readonly CarryImportFileFromPeriod $imports,
        private readonly CarryChannelPeriodState $channels,
        private readonly CarryIntegrityCheck $integrity,
        private readonly IntegrityRunner $integrityRunner,
    ) {}

    public function handle(
        Period $source,
        int $targetYear,
        string $idempotencyKey,
    ): CarryResult {
        Gate::authorize('periods.create');
        Gate::authorize('periods.update');
        Gate::authorize('periods.cancel');

        $source->loadMissing('company');

        /** @var array<string,mixed> $stored */
        $stored = IdempotencyKey::runMaster(
            $idempotencyKey,
            'period.carry:'.$source->id.':'.$targetYear,
            fn (): array => $this->execute($source->refresh(), $targetYear, $idempotencyKey),
        );

        return CarryResult::fromArray($stored);
    }

    /** @return array<string,mixed> */
    private function execute(
        Period $source,
        int $targetYear,
        string $idempotencyKey,
    ): array {
        $completed = $this->completedCarry($source, $targetYear);

        if ($completed) {
            return (new CarryResult(
                sourcePeriodId: (int) $source->id,
                targetPeriodId: (int) $completed->id,
                sourceYear: (int) $source->year,
                targetYear: (int) $completed->year,
                details: ['already_completed' => true],
            ))->toArray();
        }

        $preview = $this->preview->handle($source, $targetYear);
        $blocks = collect($preview->checks)
            ->where('status', 'block')
            ->pluck('message')
            ->values()
            ->all();

        if ($blocks !== []) {
            throw new DomainException(
                'Dönem devri preflight bloklandı: '.implode(' | ', $blocks),
            );
        }

        $target = Period::query()
            ->where('company_id', $source->company_id)
            ->where('year', $targetYear)
            ->first();

        if (! $target) {
            $target = $this->createPeriod->handle($source->company, $targetYear);
        }

        $this->claimTarget($source, $target);

        $oldCompanyId = PeriodContext::companyId();
        $oldPeriodId = PeriodContext::periodId();

        try {
            PeriodContext::useSystem((int) $target->company_id, (int) $target->id);

            $cardResult = $this->cards->handle($source, $target);
            $openingResult = $this->openings->handle($source, $target);
            $orderResult = $this->orders->handle($source, $target);

            $importIds = PeriodContext::withinSystem(
                $source,
                fn (): array => DB::connection('period')
                    ->table('import_files')
                    ->whereIn('status', ['draft', 'in_transit', 'customs'])
                    ->whereNull('received_at')
                    ->whereNull('closed_at')
                    ->orderBy('id')
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
                    ->all(),
            );

            foreach ($importIds as $sourceImportFileId) {
                $this->imports->handle(
                    sourcePeriod: $source,
                    sourceImportFileId: $sourceImportFileId,
                    idempotencyKey: hash(
                        'sha256',
                        $idempotencyKey.':import:'.$sourceImportFileId,
                    ),
                    asPeriodCarry: true,
                );
            }

            $channelResult = $this->channels->handle(
                sourcePeriod: $source,
                salesOrderCarryMap: $orderResult['sales_order_map'],
                idempotencyKey: hash('sha256', $idempotencyKey.':channels'),
            );

            $integrity = $this->integrityRunner->run($this->integrity);

            if ($integrity->mismatchCount() > 0) {
                throw new DomainException(
                    'integrity:carry mismatch bulundu; source dönem kapatılmadı.',
                );
            }

            $finalPreview = $this->preview->handle($source->refresh(), $targetYear);
            $finalBlocks = collect($finalPreview->checks)
                ->where('status', 'block')
                ->pluck('message')
                ->values()
                ->all();

            if ($finalBlocks !== []) {
                throw new DomainException(
                    'Dönem devri final preflight bloklandı: '.implode(' | ', $finalBlocks),
                );
            }

            $this->finalizeMasterState($source, $target);

            AuditContext::period(
                'Dönem devri business aşaması tamamlandı.',
                [
                    'source_period_id' => (int) $source->id,
                    'target_period_id' => (int) $target->id,
                    'source_year' => (int) $source->year,
                    'target_year' => (int) $target->year,
                    'access_copy_required' => true,
                ],
                null,
                'period_carry_completed',
            );

            AuditContext::master(
                'Dönem devri tamamlandı; kullanıcı erişim kopyası bekliyor.',
                [
                    'source_period_id' => (int) $source->id,
                    'target_period_id' => (int) $target->id,
                    'source_year' => (int) $source->year,
                    'target_year' => (int) $target->year,
                ],
                $target->refresh(),
                'period_carried',
            );

            return (new CarryResult(
                sourcePeriodId: (int) $source->id,
                targetPeriodId: (int) $target->id,
                sourceYear: (int) $source->year,
                targetYear: (int) $target->year,
                details: [
                    'cards' => $cardResult,
                    'openings' => $openingResult,
                    'orders' => [
                        'sales_orders' => $orderResult['sales_orders'],
                        'purchase_orders' => $orderResult['purchase_orders'],
                        'reservations' => $orderResult['reservations'],
                    ],
                    'imports' => count($importIds),
                    'channels' => $channelResult,
                    'integrity_checked' => $integrity->checked,
                    'access_copy_required' => true,
                ],
            ))->toArray();
        } finally {
            PeriodContext::clear();

            if ($oldCompanyId && $oldPeriodId) {
                PeriodContext::useSystem($oldCompanyId, $oldPeriodId);
            }
        }
    }

    private function completedCarry(Period $source, int $targetYear): ?Period
    {
        $target = Period::query()
            ->where('company_id', $source->company_id)
            ->where('year', $targetYear)
            ->first();

        if (! $target) {
            return null;
        }

        if ((int) ($target->carried_from_period_id ?? 0) !== (int) $source->id
            || $target->carried_at === null
            || (string) $source->status !== 'closed'
            || $source->carried_at === null) {
            return null;
        }

        return $target;
    }

    private function claimTarget(Period $source, Period $target): void
    {
        if ((int) $source->company_id !== (int) $target->company_id
            || (int) $target->year !== (int) $source->year + 1) {
            throw new DomainException('Carry target aynı şirketin ardışık dönemi olmalıdır.');
        }

        DB::connection('master')->transaction(function () use ($source, $target): void {
            $locked = Period::query()->lockForUpdate()->findOrFail($target->id);

            if ($locked->carried_at !== null) {
                throw new DomainException('Hedef dönem carry işlemi zaten tamamlanmış.');
            }

            if ($locked->carried_from_period_id !== null
                && (int) $locked->carried_from_period_id !== (int) $source->id) {
                throw new DomainException('Hedef dönem farklı bir source carry tarafından sahiplenilmiş.');
            }

            if ($locked->carried_from_period_id === null) {
                $locked->carried_from_period_id = (int) $source->id;
                $locked->version = (int) $locked->version + 1;
                $locked->save();
            }
        }, attempts: 3);
    }

    private function finalizeMasterState(Period $source, Period $target): void
    {
        DB::connection('master')->transaction(function () use ($source, $target): void {
            $sourceLocked = Period::query()->lockForUpdate()->findOrFail($source->id);
            $targetLocked = Period::query()->lockForUpdate()->findOrFail($target->id);

            if ((string) $sourceLocked->status !== 'active') {
                throw new DomainException('Finalizasyon anında source dönem active olmalıdır.');
            }

            if ((int) ($targetLocked->carried_from_period_id ?? 0) !== (int) $sourceLocked->id
                || $targetLocked->carried_at !== null) {
                throw new DomainException('Target carry ownership/finalizasyon durumu geçersiz.');
            }

            $now = now();

            $sourceLocked->status = 'closed';
            $sourceLocked->closed_at = $now;
            $sourceLocked->carried_at = $now;
            $sourceLocked->version = (int) $sourceLocked->version + 1;
            $sourceLocked->save();

            $targetLocked->carried_at = $now;
            $targetLocked->version = (int) $targetLocked->version + 1;
            $targetLocked->save();
        }, attempts: 3);
    }
}
