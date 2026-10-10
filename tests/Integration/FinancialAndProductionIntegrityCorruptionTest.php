<?php

use App\Support\Integrity\Checks\ContactBalanceCheck;
use App\Support\Integrity\Checks\FinanceIntegrityCheck;
use App\Support\Integrity\Checks\RecipeIntegrityCheck;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\IsolatedPostgres;

it('detects a posted sales invoice without its required contact ledger entry', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $db = DB::connection('period');
        $invoiceId = $db->table('documents')->insertGetId([
            'document_type' => 'sales_invoice',
            'document_date' => '2026-10-10',
            'status' => 'posted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = app(ContactBalanceCheck::class)->run();
        expect(collect($result->mismatches)->contains(
            fn (array $mismatch): bool => (int) ($mismatch['document_id'] ?? 0) === $invoiceId
                && ($mismatch['reason'] ?? '') === 'contact_transaction_count',
        ))->toBeTrue();
    });
});

it('detects an active manufacturing recipe with no material components', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $ids = IsolatedPostgres::productAndLocation();
        $recipeId = DB::connection('period')->table('production_recipes')->insertGetId([
            'product_id' => $ids['product'],
            'number' => 'V4-R-'.Str::random(12),
            'revision_no' => 1,
            'output_quantity' => '1.000',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = app(RecipeIntegrityCheck::class)->run();
        expect(collect($result->mismatches)->contains(
            fn (array $mismatch): bool => (int) ($mismatch['recipe_id'] ?? 0) === $recipeId
                && ($mismatch['reason'] ?? '') === 'recipe_header_invalid',
        ))->toBeTrue();
    });
});

it('identifies a one-sided transfer as an unbalanced cash or bank movement group', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $db = DB::connection('period');
        $accountId = $db->table('cash_accounts')->insertGetId([
            'code' => 'V4'.Str::random(9),
            'name' => 'V4 Integrity Cash Account',
            'currency' => 'TRY',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $group = 'v4-unbalanced-'.Str::random(18);
        $db->table('cash_movements')->insert([
            'cash_account_id' => $accountId,
            'movement_date' => '2026-10-10',
            'movement_type' => 'transfer',
            'group_key' => $group,
            'direction' => 'out',
            'amount' => '25.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = app(FinanceIntegrityCheck::class)->run();
        expect(collect($result->mismatches)->contains(
            fn (array $mismatch): bool => ($mismatch['group_key'] ?? '') === $group
                && ($mismatch['reason'] ?? '') === 'finance_transfer_unbalanced',
        ))->toBeTrue();
    });
});
