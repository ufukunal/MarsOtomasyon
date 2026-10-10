<?php

use App\Actions\Sales\CalculateSalesRiskProjection;
use App\Models\Period\Contact;
use App\Models\Period\Document;
use Illuminate\Support\Facades\DB;
use Tests\Support\IsolatedPostgres;

it('includes existing receivables in credit risk and flags only an exceeded known limit', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $contact = Contact::query()->create([
            'title' => 'V4 risk customer',
            'type' => 'legal',
            'risk_limit' => '100.0000',
        ]);
        DB::connection('period')->table('contact_transactions')->insert([
            'contact_id' => $contact->id,
            'transaction_type' => 'sales_invoice',
            'direction' => 'debit',
            'transaction_date' => '2026-10-10',
            'amount' => '60.0000',
            'currency' => 'TRY',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $order = new Document(['grand_total' => '45.0000', 'contact_id' => $contact->id]);
        $risk = app(CalculateSalesRiskProjection::class)->handle($order);

        expect($risk->currentBalance)->toBe('60.0000')
            ->and($risk->knownExposure)->toBe('105.0000')
            ->and($risk->knownLimitExceeded)->toBeTrue()
            ->and($risk->knownOverLimit)->toBe('5.0000')
            ->and($risk->projectionComplete)->toBeFalse();
    });
});

it('does not flag a credit-limit exceedance when the customer limit is unlimited', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $contact = Contact::query()->create([
            'title' => 'V4 unlimited',
            'type' => 'legal',
            'risk_limit' => '0.0000',
        ]);
        $order = new Document(['grand_total' => '9999.0000', 'contact_id' => $contact->id]);
        $risk = app(CalculateSalesRiskProjection::class)->handle($order);

        expect($risk->knownLimitExceeded)->toBeFalse()
            ->and($risk->knownOverLimit)->toBe('0.0000');
    });
});
