<?php

use App\Actions\Documents\SaveSalesDocumentDraft;
use App\Actions\Finance\PostContactDebitCredit;
use App\Enums\DocumentType;
use App\Exceptions\PeriodClosedException;
use App\Exceptions\StaleRecordException;
use App\Models\Period\Document;
use App\Support\Period\PeriodContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('Faz 3 belgelerini iki fiziksel period veritabanı arasında izole eder', function () {
    [$companyA, $periodA] = $this->createCompanyWithPeriod('F3ISOA');
    [$companyB, $periodB] = $this->createCompanyWithPeriod('F3ISOB');

    PeriodContext::useSystem($companyA->id, $periodA->id);

    app(SaveSalesDocumentDraft::class)->handle(
        DocumentType::Quote,
        ['document_date' => '2026-05-01'],
        [[
            'line_kind' => 'service',
            'description' => 'A şirketi',
            'quantity' => '1.000',
            'unit_price' => '10.0000',
            'vat_rate' => '20.0000',
        ]],
    );

    expect(Document::query()->count())->toBe(1);

    PeriodContext::useSystem($companyB->id, $periodB->id);

    expect(Document::query()->count())->toBe(0);
});

it('Faz 3 taslak belge stale version güncellemesini reddeder', function () {
    [$company, $period] = $this->createCompanyWithPeriod('F3STALE');
    PeriodContext::useSystem($company->id, $period->id);

    $document = app(SaveSalesDocumentDraft::class)->handle(
        DocumentType::Quote,
        ['document_date' => '2026-05-01'],
        [[
            'line_kind' => 'service',
            'description' => 'Optimistic',
            'quantity' => '1.000',
            'unit_price' => '10.0000',
            'vat_rate' => '20.0000',
        ]],
    );

    $version = (int) $document->version;
    $document->updateWithVersion(['notes' => 'ilk güncelleme'], $version);

    expect(fn () => $document->updateWithVersion(['notes' => 'stale güncelleme'], $version))
        ->toThrow(StaleRecordException::class);
});

it('yanlış period yılı postingini numara sayacını tüketmeden reddeder', function () {
    [$company, $period] = $this->createCompanyWithPeriod('F3YEAR', 2026);
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);

    $contact = \App\Models\Period\Contact::query()->create([
        'title' => 'Yıl Sınırı',
        'type' => 'legal',
        'risk_limit' => '0.0000',
        'discount_rate' => '0.0000',
        'is_active' => true,
    ]);

    $before = DB::connection('period')->table('number_series')->sum('last_number');

    expect(fn () => app(PostContactDebitCredit::class)->handle(
        $contact->id,
        'debit',
        '10.0000',
        '2027-01-02',
        'Yıl sınırı testi',
        (string) Str::uuid(),
    ))->toThrow(PeriodClosedException::class);

    expect(DB::connection('period')->table('number_series')->sum('last_number'))->toBe($before)
        ->and(Document::query()->whereYear('document_date', 2027)->count())->toBe(0);
});
