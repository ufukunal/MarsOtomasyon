<?php

use App\Actions\Finance\PostContactTransfer;
use App\Actions\Finance\ReverseContactTransfer;
use App\Models\Period\Contact;
use App\Models\Period\ContactTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AuthorizedPeriod;
use Tests\Support\IsolatedPostgres;

it('moves TRY balances between two caris once, without cash or bank movement, then reverses both entries', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $source = Contact::query()->create(['title' => 'Transfer source '.Str::random(7), 'type' => 'legal']);
            $target = Contact::query()->create(['title' => 'Transfer target '.Str::random(7), 'type' => 'legal']);
            ContactTransaction::query()->create([
                'contact_id' => $source->id, 'transaction_type' => 'opening',
                'direction' => 'debit', 'transaction_date' => '2026-10-10',
                'amount' => '100.0000', 'currency' => 'TRY',
            ]);
            $beforeCash = DB::connection('period')->table('cash_movements')->count();
            $beforeBank = DB::connection('period')->table('bank_movements')->count();

            $key = 'cari-transfer-'.Str::random(20);
            $posting = app(PostContactTransfer::class);
            $transfer = $posting->handle(
                $source->id, $target->id, 'debit', '30', '2026-10-10', 'Integration transfer', $key,
            );
            expect($source->fresh()->balance())->toBe('70.0000')
                ->and($target->fresh()->balance())->toBe('30.0000')
                ->and(DB::connection('period')->table('contact_transactions')
                    ->whereIn('id', [$transfer->source_transaction_id, $transfer->target_transaction_id])->count())->toBe(2)
                ->and(DB::connection('period')->table('cash_movements')->count())->toBe($beforeCash)
                ->and(DB::connection('period')->table('bank_movements')->count())->toBe($beforeBank);

            $postedEntries = ContactTransaction::query()->whereIn('id', [
                $transfer->source_transaction_id, $transfer->target_transaction_id,
            ])->get();
            expect($postedEntries)->toHaveCount(2)
                ->and($postedEntries->pluck('direction')->sort()->values()->all())->toBe(['credit', 'debit'])
                ->and($postedEntries->pluck('amount')->map(fn ($amount): string => (string) $amount)->unique()->count())->toBe(1);

            $replayed = $posting->handle(
                $source->id, $target->id, 'debit', '30', '2026-10-10', 'Integration transfer', $key,
            );
            expect($replayed->id)->toBe($transfer->id);

            $reversalKey = 'reverse-'.Str::random(20);
            $reversed = app(ReverseContactTransfer::class)->handle(
                $transfer, '2026-10-10', 'Integration correction', $reversalKey,
            );
            expect($reversed->reversal_of_id)->toBe($transfer->id)
                ->and($source->fresh()->balance())->toBe('100.0000')
                ->and($target->fresh()->balance())->toBe('0.0000')
                ->and(DB::connection('period')->table('contact_transactions')
                    ->whereIn('reversal_of_id', [$transfer->source_transaction_id, $transfer->target_transaction_id])
                    ->count())->toBe(2)
                ->and(DB::connection('period')->table('cash_movements')->count())->toBe($beforeCash)
                ->and(DB::connection('period')->table('bank_movements')->count())->toBe($beforeBank);

            $replayedReversal = app(ReverseContactTransfer::class)->handle(
                $transfer, '2026-10-10', 'Integration correction',
                $reversalKey,
            );
            expect($replayedReversal->id)->toBe($reversed->id);

            expect(fn () => app(ReverseContactTransfer::class)->handle(
                $transfer, '2026-10-10', 'Duplicate', 'reverse-'.Str::random(20),
            ))->toThrow(DomainException::class);
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});

it('requires explicit authority and reason for an overdrawn transfer', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $source = Contact::query()->create(['title' => 'Empty source '.Str::random(8), 'type' => 'legal']);
            $target = Contact::query()->create(['title' => 'Empty target '.Str::random(8), 'type' => 'legal']);
            $post = app(PostContactTransfer::class);

            expect(fn () => $post->handle(
                $source->id, $target->id, 'debit', '10', '2026-10-10', 'Reason',
                'overdraft-'.Str::random(20),
            ))->toThrow(DomainException::class);

            expect(fn () => $post->handle(
                $source->id, $target->id, 'debit', '10', '2026-10-10', '',
                'overdraft-'.Str::random(20), true,
            ))->toThrow(DomainException::class);

            $transfer = $post->handle(
                $source->id, $target->id, 'debit', '10', '2026-10-10',
                'Approved exceptional transfer', 'overdraft-'.Str::random(20), true,
            );
            expect($transfer->overdraft_approved)->toBeTrue()
                ->and($source->fresh()->balance())->toBe('-10.0000')
                ->and($target->fresh()->balance())->toBe('10.0000');
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});
