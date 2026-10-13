<?php

use App\Actions\Sales\CalculateSalesRiskProjection;
use App\Models\Period\Contact;
use App\Models\Period\Document;
use App\Queries\Finance\BuildContactAging;
use Illuminate\Support\Facades\DB;
use Tests\Support\IsolatedPostgres;

it('ages TRY and USD separately without using foreign-currency credits against TRY debits', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $contact = Contact::query()->create(['title' => 'Aging multi currency', 'type' => 'legal']);
        foreach ([
            ['debit', 'TRY', '100.0000'],
            ['credit', 'USD', '100.0000'],
        ] as [$direction, $currency, $amount]) {
            DB::connection('period')->table('contact_transactions')->insert([
                'contact_id' => $contact->id,
                'transaction_type' => 'opening',
                'direction' => $direction,
                'currency' => $currency,
                'transaction_date' => '2026-10-01',
                'amount' => $amount,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $query = app(BuildContactAging::class);
        $try = $query->handle($contact->id, '2026-10-14', 'TRY');
        $usd = $query->handle($contact->id, '2026-10-14', 'USD');

        expect($try->currency)->toBe('TRY')
            ->and($try->balance)->toBe('100.0000')
            ->and($try->openDebit)->toBe('100.0000')
            ->and($usd->currency)->toBe('USD')
            ->and($usd->balance)->toBe('-100.0000')
            ->and($usd->excessCredit)->toBe('100.0000');
    });
});

it('rejects non-TRY sales risk projection rather than treating USD as TRY', function (): void {
    $order = new Document(['contact_id' => 1, 'currency' => 'USD', 'grand_total' => '500.0000']);
    expect(fn () => app(CalculateSalesRiskProjection::class)->handle($order))
        ->toThrow(\DomainException::class, 'TRY dışındaki');
});
