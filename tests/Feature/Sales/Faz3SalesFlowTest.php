<?php

use App\Actions\Documents\CalculateDocumentTotals;
use App\Actions\Documents\SaveSalesDocumentDraft;
use App\Actions\Sales\ApproveQuote;
use App\Actions\Sales\ConfirmSalesOrder;
use App\Actions\Sales\ConvertQuoteToSalesOrder;
use App\Actions\Sales\CreateDispatchFromOrder;
use App\Actions\Sales\CreateQuoteRevision;
use App\Actions\Sales\PostDispatch;
use App\Actions\Sales\ReserveSalesOrderLines;
use App\Actions\Sales\SendQuoteToCustomerReview;
use App\Actions\Sales\SubmitQuoteForInternalApproval;
use App\Actions\Stock\RecordStockMovement;
use App\DataObjects\StockMovementData;
use App\Enums\DocumentType;
use App\Models\Period\Contact;
use App\Models\Period\Document;
use App\Models\Period\Location;
use App\Models\Period\StockBalance;
use App\Models\Period\StockReservation;
use Illuminate\Support\Str;

function faz3SalesContact(string $title = 'Faz 3 Müşteri'): Contact
{
    return Contact::query()->create([
        'title' => $title,
        'type' => 'legal',
        'term_days' => 30,
        'risk_limit' => '1000000.0000',
        'discount_rate' => '0.0000',
        'is_active' => true,
    ]);
}

function faz3SalesStockIn(int $productId, int $locationId, string $quantity): void
{
    app(RecordStockMovement::class)->handle(new StockMovementData(
        productId: $productId,
        locationId: $locationId,
        movementDate: '2026-03-01',
        direction: 'in',
        reason: 'purchase',
        quantity: $quantity,
        unitCost: '10.0000',
        updatesAverage: true,
        actorUserId: auth()->id(),
        actorUserName: auth()->user()?->name,
    ));
}

it('Faz 3 hesap motoru iskonto KDV ve yuvarlamayı sabit beklenen değerlerle hesaplar', function () {
    $totals = app(CalculateDocumentTotals::class)->handle([
        [
            'quantity' => '2.000',
            'unit_price' => '100.0000',
            'line_discount_rate' => '10.0000',
            'line_discount_amount' => '0',
            'vat_rate' => '20.0000',
        ],
        [
            'quantity' => '1.000',
            'unit_price' => '50.0000',
            'line_discount_rate' => '0',
            'line_discount_amount' => '0',
            'vat_rate' => '10.0000',
        ],
    ], '10.0000', '0');

    expect($totals->lines[0]->lineTotal)->toBe('180.0000')
        ->and($totals->lines[1]->lineTotal)->toBe('50.0000')
        ->and($totals->subtotal)->toBe('230.0000')
        ->and($totals->discountAmount)->toBe('23.0000')
        ->and($totals->taxBase)->toBe('207.0000')
        ->and($totals->vatAmount)->toBe('36.9000')
        ->and($totals->roundingDifference)->toBe('0.0000')
        ->and($totals->grandTotal)->toBe('243.9000');
});

it('teklif draft numarasız başlar onaylanır ve source line zinciriyle siparişe dönüşür', function () {
    [$company, $period] = $this->createCompanyWithPeriod('F3QUOTE');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);

    $contact = faz3SalesContact();

    $quote = app(SaveSalesDocumentDraft::class)->handle(
        DocumentType::Quote,
        [
            'document_date' => '2026-03-05',
            'valid_until' => '2026-03-20',
            'contact_id' => $contact->id,
        ],
        [[
            'line_kind' => 'service',
            'description' => 'Danışmanlık',
            'quantity' => '1.000',
            'unit_price' => '100.0000',
            'vat_rate' => '20.0000',
        ]],
    );

    expect($quote->revision_no)->toBe(1)
        ->and($quote->number)->toBeNull()
        ->and($quote->status)->toBe('draft');

    $quote = app(SubmitQuoteForInternalApproval::class)->handle($quote, (string) Str::uuid());
    expect($quote->status)->toBe('internal_review')
        ->and($quote->number)->not->toBeNull();

    $quote = app(ApproveQuote::class)->handle($quote, (string) Str::uuid());
    expect($quote->status)->toBe('approved');

    $order = app(ConvertQuoteToSalesOrder::class)->handle($quote, (string) Str::uuid());
    $sourceLine = $quote->lines()->firstOrFail();

    expect($quote->refresh()->status)->toBe('converted')
        ->and($order->document_type)->toBe(DocumentType::SalesOrder)
        ->and($order->status)->toBe('draft')
        ->and($order->lines)->toHaveCount(1)
        ->and((int) $order->lines->first()->source_line_id)->toBe((int) $sourceLine->id);
});

it('teklif revizyonları aynı ana numarada artar ve stale revizyondan branch açılmaz', function () {
    [$company, $period] = $this->createCompanyWithPeriod('F3REV');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);

    $quote = app(SaveSalesDocumentDraft::class)->handle(
        DocumentType::Quote,
        ['document_date' => '2026-04-01'],
        [[
            'line_kind' => 'service',
            'description' => 'Revizyon hizmeti',
            'quantity' => '1.000',
            'unit_price' => '50.0000',
            'vat_rate' => '20.0000',
        ]],
    );

    $quote = app(SendQuoteToCustomerReview::class)->handle($quote, (string) Str::uuid());
    $key = (string) Str::uuid();
    $revision = app(CreateQuoteRevision::class)->handle($quote, $key);
    $retry = app(CreateQuoteRevision::class)->handle($quote, $key);

    expect($revision->revision_no)->toBe(2)
        ->and($revision->number)->toBe($quote->number)
        ->and($retry->id)->toBe($revision->id)
        ->and(Document::query()->where('document_type', 'quote')->where('number', $quote->number)->count())->toBe(2);

    expect(fn () => app(CreateQuoteRevision::class)->handle($quote, (string) Str::uuid()))
        ->toThrow(DomainException::class);
});

it('sipariş rezervasyonu fiziksel stoğu düşürmez ve partial irsaliye posting rezervasyonu tüketir', function () {
    [$company, $period] = $this->createCompanyWithPeriod('F3ORDER');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);

    $contact = faz3SalesContact();
    $product = $this->createTestProduct(['code' => 'F3-P']);
    $location = Location::query()->create([
        'code' => 'F3-WH',
        'name' => 'Faz 3 Depo',
        'kind' => 'warehouse',
        'is_default' => false,
        'is_active' => true,
    ]);
    faz3SalesStockIn($product->id, $location->id, '10.000');

    $order = app(SaveSalesDocumentDraft::class)->handle(
        DocumentType::SalesOrder,
        [
            'document_date' => '2026-03-05',
            'contact_id' => $contact->id,
        ],
        [[
            'line_kind' => 'stock',
            'product_id' => $product->id,
            'unit_id' => $product->unit_id,
            'quantity' => '6.000',
            'conversion_factor' => '1.000000',
            'location_id' => $location->id,
            'unit_price' => '100.0000',
            'vat_rate' => '20.0000',
            'reserve_stock' => true,
        ]],
    );

    $order = app(ConfirmSalesOrder::class)->handle($order, (string) Str::uuid(), true);
    $line = $order->lines()->firstOrFail();

    app(ReserveSalesOrderLines::class)->handle(
        $order,
        [$line->id => [$location->id]],
        (string) Str::uuid(),
    );

    $balance = StockBalance::query()
        ->where('product_id', $product->id)
        ->where('location_id', $location->id)
        ->firstOrFail();

    expect((string) $balance->quantity)->toBe('10.000')
        ->and((string) $balance->reserved)->toBe('6.000');

    $dispatch = app(CreateDispatchFromOrder::class)->handle(
        $order,
        [$line->id => '4.000'],
        [$line->id => $location->id],
        '2026-03-06',
        (string) Str::uuid(),
    );
    $dispatch = app(PostDispatch::class)->handle($dispatch, (string) Str::uuid());

    $balance->refresh();

    expect($dispatch->status)->toBe('posted')
        ->and((string) $balance->quantity)->toBe('6.000')
        ->and((string) $balance->reserved)->toBe('2.000')
        ->and(StockReservation::query()
            ->where('document_line_id', $line->id)
            ->where('status', 'active')
            ->sum('quantity'))->toEqual(2);

    expect(fn () => app(CreateDispatchFromOrder::class)->handle(
        $order,
        [$line->id => '3.000'],
        [$line->id => $location->id],
        '2026-03-07',
        (string) Str::uuid(),
    ))->toThrow(DomainException::class);
});
