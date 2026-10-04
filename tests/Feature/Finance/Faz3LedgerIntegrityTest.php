<?php

use App\Actions\Documents\ReverseDocument;
use App\Actions\Finance\PostCollection;
use App\Actions\Finance\PostContactDebitCredit;
use App\Actions\Finance\SaveCashAccount;
use App\Models\Period\CashMovement;
use App\Models\Period\Contact;
use App\Models\Period\ContactTransaction;
use App\Models\Period\Document;
use App\Queries\Finance\BuildContactAging;
use App\Support\Integrity\Checks\ContactBalanceCheck;
use App\Support\Integrity\Checks\DocumentTotalCheck;
use App\Support\Integrity\Checks\NumberSeriesCheck;
use App\Support\Integrity\Checks\PartialDocumentCheck;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function faz3FinanceContact(string $title = 'Faz 3 Cari'): Contact
{
    return Contact::query()->create([
        'title' => $title,
        'type' => 'legal',
        'term_days' => 30,
        'risk_limit' => '1000.0000',
        'discount_rate' => '0.0000',
        'is_active' => true,
    ]);
}

it('cari debit collection ve aging FIFO aynı ledger bakiyesini üretir', function () {
    [$company, $period] = $this->createCompanyWithPeriod('F3LEDGER');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);

    $contact = faz3FinanceContact();
    $cash = app(SaveCashAccount::class)->handle([
        'code' => 'KASA-F3',
        'name' => 'Faz 3 Kasa',
        'currency' => 'TRY',
        'is_active' => true,
    ]);

    app(PostContactDebitCredit::class)->handle(
        $contact->id,
        'debit',
        '120.0000',
        '2026-06-01',
        'Test borcu',
        (string) Str::uuid(),
    );

    $key = (string) Str::uuid();
    $collection = app(PostCollection::class)->handle(
        $contact->id,
        '20.0000',
        '2026-06-10',
        'cash',
        $cash->id,
        $key,
    );
    $retry = app(PostCollection::class)->handle(
        $contact->id,
        '20.0000',
        '2026-06-10',
        'cash',
        $cash->id,
        $key,
    );

    $aging = app(BuildContactAging::class)->handle($contact->id, '2026-06-30');

    expect($retry->id)->toBe($collection->id)
        ->and($contact->refresh()->balance())->toBe('100.0000')
        ->and($aging->balance)->toBe('100.0000')
        ->and($aging->openDebit)->toBe('100.0000')
        ->and($aging->excessCredit)->toBe('0.0000')
        ->and(ContactTransaction::query()->where('contact_id', $contact->id)->count())->toBe(2)
        ->and(CashMovement::query()->where('document_id', $collection->id)->count())->toBe(1);
});

it('manual cari fiş ters kaydı exact inverse üretir ve aging bakiyesini nötrler', function () {
    [$company, $period] = $this->createCompanyWithPeriod('F3REVERSAL');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);

    $contact = faz3FinanceContact('Ters Kayıt Cari');
    $original = app(PostContactDebitCredit::class)->handle(
        $contact->id,
        'debit',
        '75.0000',
        '2026-07-01',
        'Manuel borç',
        (string) Str::uuid(),
    );

    $reversal = app(ReverseDocument::class)->handle(
        $original,
        '2026-07-02',
        'Hatalı kayıt',
        (string) Str::uuid(),
    );

    $aging = app(BuildContactAging::class)->handle($contact->id, '2026-07-31');

    expect($reversal->document_type)->toBe($original->document_type)
        ->and($reversal->id)->not->toBe($original->id)
        ->and($contact->refresh()->balance())->toBe('0.0000')
        ->and($aging->balance)->toBe('0.0000')
        ->and($aging->openDebit)->toBe('0.0000')
        ->and(ContactTransaction::query()->where('contact_id', $contact->id)->count())->toBe(2);
});

it('Faz 3 integrity kontrolleri temiz veride yeşildir ve number series sapmasını raporlar', function () {
    [$company, $period] = $this->createCompanyWithPeriod('F3INTEGRITY');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);

    $contact = faz3FinanceContact('Integrity Cari');

    app(PostContactDebitCredit::class)->handle(
        $contact->id,
        'debit',
        '10.0000',
        '2026-08-01',
        'Integrity',
        (string) Str::uuid(),
    );

    expect(app(DocumentTotalCheck::class)->run()->mismatchCount())->toBe(0)
        ->and(app(ContactBalanceCheck::class)->run()->mismatchCount())->toBe(0)
        ->and(app(PartialDocumentCheck::class)->run()->mismatchCount())->toBe(0)
        ->and(app(NumberSeriesCheck::class)->run()->mismatchCount())->toBe(0);

    DB::connection('period')->table('number_series')
        ->where('document_type', 'contact_debit_credit')
        ->where('year', 2026)
        ->update(['last_number' => 0]);

    $result = app(NumberSeriesCheck::class)->run();

    expect($result->mismatchCount())->toBeGreaterThan(0)
        ->and(collect($result->mismatches)->pluck('reason'))->toContain('series_behind_documents');
});
