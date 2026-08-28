<?php

use App\Foundation\Identity\SourceEffectIdentity;
use App\Modules\Core\Enums\PostingPeriodStatus;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\PostingPeriod;
use App\Modules\Treasury\Ledger\PostTreasuryMovementData;
use App\Modules\Treasury\Ledger\TreasuryMovementPoster;
use App\Modules\Treasury\Models\TreasuryAccount;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;

uses(DatabaseMigrations::class);

it('makes matched bank statement evidence terminal and undeletable', function (): void {
    $company = Company::query()->create([
        'code' => 'M10-STMT-AUTH',
        'name' => 'M10 Statement Authority',
    ]);

    PostingPeriod::query()->create([
        'company_id' => $company->getKey(),
        'code' => '2026-08',
        'name' => 'Ağustos 2026',
        'starts_on' => '2026-08-01',
        'ends_on' => '2026-08-31',
        'status' => PostingPeriodStatus::Open,
        'closed_at' => null,
    ]);

    $bank = TreasuryAccount::query()->create([
        'company_id' => $company->getKey(),
        'type' => 'bank',
        'code' => 'BANK-AUTH',
        'name' => 'Authority Bank',
        'currency_code' => 'TRY',
        'is_active' => true,
        'bank_name' => 'Authority Bank',
    ]);

    $movement = DB::transaction(fn () => app(TreasuryMovementPoster::class)->post(
        new PostTreasuryMovementData(
            sourceEffect: new SourceEffectIdentity(
                (int) $company->getKey(),
                'm10_statement_authority_test',
                'movement-1',
                'treasury.bank_in',
            ),
            treasuryAccountId: (int) $bank->getKey(),
            postingDate: '2026-08-28',
            signedAmount: '125.500000',
            movementType: 'bank_in',
            memo: 'Statement authority fixture',
        ),
    ));

    $importId = (int) DB::table('bank_statement_imports')->insertGetId([
        'company_id' => $company->getKey(),
        'treasury_account_id' => $bank->getKey(),
        'format' => 'csv',
        'file_name' => 'authority.csv',
        'file_hash' => hash('sha256', 'authority.csv'),
        'line_count' => 1,
        'created_at' => now(),
    ]);

    $lineId = (int) DB::table('bank_statement_lines')->insertGetId([
        'company_id' => $company->getKey(),
        'bank_statement_import_id' => $importId,
        'treasury_account_id' => $bank->getKey(),
        'external_key' => 'AUTH-1',
        'booking_date' => '2026-08-28',
        'value_date' => null,
        'currency_code' => 'TRY',
        'signed_amount' => '125.500000',
        'reference' => 'AUTH-1',
        'description' => 'Authority fixture',
        'match_status' => 'unmatched',
        'matched_treasury_movement_id' => null,
        'matched_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $matchedAt = now();
    DB::table('bank_statement_lines')->where('id', $lineId)->update([
        'match_status' => 'matched',
        'matched_treasury_movement_id' => $movement->getKey(),
        'matched_at' => $matchedAt,
        'updated_at' => $matchedAt,
    ]);

    expect((string) DB::table('bank_statement_lines')->where('id', $lineId)->value('match_status'))
        ->toBe('matched');

    expect(fn () => DB::table('bank_statement_lines')->where('id', $lineId)->update([
        'matched_at' => $matchedAt->copy()->addSecond(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    expect(fn () => DB::table('bank_statement_lines')->where('id', $lineId)->delete())
        ->toThrow(QueryException::class);
});
