<?php

use App\Actions\Numbering\GenerateDocumentNumber;
use App\Exceptions\PeriodReadOnlyException;
use App\Models\Period;
use App\Support\Concurrency\IdempotencyKey;
use App\Support\Period\PeriodContext;
use Illuminate\Support\Facades\DB;
use Tests\Support\IsolatedPostgres;

it('blocks numbering and idempotent mutations immediately when the active period is closed', function (): void {
    IsolatedPostgres::withActivePeriod(function (int $companyId, int $periodId): void {
        $master = DB::connection('master');
        $db = DB::connection('period');

        expect(PeriodContext::companyId())->toBe($companyId);
        expect(PeriodContext::periodId())->toBe($periodId);

        $period = Period::query()->findOrFail($periodId);
        $period->status = 'closed';
        $period->save();

        $numbersBefore = $db->table('number_series')->count();
        $keysBefore = $db->table('idempotency_keys')->count();

        expect(fn () => app(GenerateDocumentNumber::class)->handle('sales_invoice', 2026))
            ->toThrow(PeriodReadOnlyException::class);
        expect(fn () => IdempotencyKey::run('v4-closed-period', 'stock.mutate', fn () => true))
            ->toThrow(PeriodReadOnlyException::class);

        expect($db->table('number_series')->count())->toBe($numbersBefore)
            ->and($db->table('idempotency_keys')->count())->toBe($keysBefore);

        expect($master->table('periods')->where('id', $periodId)->value('status'))->toBe('closed');
    });
});
