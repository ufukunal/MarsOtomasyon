<?php

use App\Actions\Contacts\SaveContact;
use App\Actions\Documents\SaveSalesDocumentDraft;
use App\Actions\Documents\SourceLineAvailability;
use App\Actions\Sales\ConfirmSalesOrder;
use App\Actions\Sales\CreateDispatchFromOrder;
use App\Actions\Sales\CreateInvoiceFromDispatches;
use App\Actions\Sales\PostDispatch;
use App\Actions\Sales\PostSalesInvoice;
use App\Actions\Stock\RecordStockMovement;
use App\DataObjects\StockMovementData;
use App\Enums\DocumentType;
use App\Models\Period\ContactTransaction;
use App\Models\Period\Location;
use App\Models\Period\StockMovement;
use DomainException;

it('kismi sevk ve kismi faturada kalan miktarlari dogru izler ve stogu ikinci kez dusmez', function () {
    [$company, $period] = $this->createCompanyWithPeriod('FAZ3PARTIAL');
    $user = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($user, $company, $period);

    $contact = app(SaveContact::class)->handle([
        'title' => 'Faz 3 Kısmi Cari',
        'type' => 'legal',
        'risk_limit' => '0',
        'discount_rate' => '0',
        'category_ids' => [],
        'is_active' => true,
    ]);
    $product = $this->createTestProduct(['code' => 'F3-PARTIAL']);
    $location = Location::query()->create([
        'code' => 'F3-PART-WH',
        'name' => 'Faz 3 Kısmi Depo',
        'kind' => 'warehouse',
        'is_default' => false,
        'is_active' => true,
    ]);

    app(RecordStockMovement::class)->handle(new StockMovementData(
        productId: $product->id,
        locationId: $location->id,
        movementDate: '2026-10-01',
        direction: 'in',
        reason: 'opening',
        quantity: '10.000',
        unitCost: '25.0000',
        updatesAverage: true,
        actorUserId: $user->id,
        actorUserName: $user->name,
    ));

    $order = app(SaveSalesDocumentDraft::class)->handle(
        DocumentType::SalesOrder,
        [
            'contact_id' => $contact->id,
            'document_date' => '2026-10-01',
            'discount_rate' => '0',
        ],
        [[
            'line_kind' => 'stock',
            'product_id' => $product->id,
            'unit_id' => $product->unit_id,
            'quantity' => '10.000',
            'location_id' => $location->id,
            'unit_price' => '100.0000',
            'line_discount_rate' => '0',
            'line_discount_amount' => '0',
            'vat_rate' => '20',
        ]],
    );

    $order = app(ConfirmSalesOrder::class)->handle(
        $order,
        'faz3-partial-order-confirm',
        true,
    )->load('lines');
    $orderLine = DocumentLine::query()->where('document_id', $order->id)->firstOrFail();

    $dispatch = app(CreateDispatchFromOrder::class)->handle(
        $order,
        [$orderLine->id => '4.000'],
        [$orderLine->id => $location->id],
        '2026-10-02',
        'faz3-partial-dispatch-create',
    );
    $dispatch = app(PostDispatch::class)->handle(
        $dispatch,
        'faz3-partial-dispatch-post',
    )->load('lines');

    $retry = app(PostDispatch::class)->handle(
        $dispatch,
        'faz3-partial-dispatch-post',
    );

    expect($retry->id)->toBe($dispatch->id)
        ->and(StockMovement::query()
            ->where('document_type', DocumentType::Dispatch->value)
            ->where('document_id', $dispatch->id)
            ->count())->toBe(1)
        ->and(app(SourceLineAvailability::class)->orderRemaining($orderLine->refresh()))->toBe('6.000');

    $dispatchLine = DocumentLine::query()->where('document_id', $dispatch->id)->firstOrFail();
    $invoice = app(CreateInvoiceFromDispatches::class)->handle(
        [$dispatchLine->id => '2.000'],
        '2026-10-03',
        'faz3-partial-invoice-create',
    );
    $invoice = app(PostSalesInvoice::class)->handle(
        $invoice,
        'faz3-partial-invoice-post',
    );

    expect(app(SourceLineAvailability::class)->dispatchRemaining($dispatchLine->refresh()))->toBe('2.000')
        ->and(StockMovement::query()
            ->where('document_type', DocumentType::SalesInvoice->value)
            ->where('document_id', $invoice->id)
            ->count())->toBe(0)
        ->and(ContactTransaction::query()->where('document_id', $invoice->id)->count())->toBe(1);
});

it('ayni siparis kalanini asan iki taslak sevkten ikincisini posting aninda reddeder', function () {
    [$company, $period] = $this->createCompanyWithPeriod('FAZ3RACE');
    $user = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($user, $company, $period);

    $contact = app(SaveContact::class)->handle([
        'title' => 'Faz 3 Yarış Cari',
        'type' => 'legal',
        'risk_limit' => '0',
        'discount_rate' => '0',
        'category_ids' => [],
        'is_active' => true,
    ]);
    $product = $this->createTestProduct(['code' => 'F3-RACE']);
    $location = Location::query()->create([
        'code' => 'F3-RACE-WH',
        'name' => 'Faz 3 Yarış Depo',
        'kind' => 'warehouse',
        'is_default' => false,
        'is_active' => true,
    ]);

    app(RecordStockMovement::class)->handle(new StockMovementData(
        productId: $product->id,
        locationId: $location->id,
        movementDate: '2026-10-01',
        direction: 'in',
        reason: 'opening',
        quantity: '10.000',
        unitCost: '25.0000',
        updatesAverage: true,
        actorUserId: $user->id,
        actorUserName: $user->name,
    ));

    $order = app(SaveSalesDocumentDraft::class)->handle(
        DocumentType::SalesOrder,
        [
            'contact_id' => $contact->id,
            'document_date' => '2026-10-01',
            'discount_rate' => '0',
        ],
        [[
            'line_kind' => 'stock',
            'product_id' => $product->id,
            'unit_id' => $product->unit_id,
            'quantity' => '5.000',
            'location_id' => $location->id,
            'unit_price' => '100.0000',
            'line_discount_rate' => '0',
            'line_discount_amount' => '0',
            'vat_rate' => '20',
        ]],
    );

    $order = app(ConfirmSalesOrder::class)->handle(
        $order,
        'faz3-race-order-confirm',
        true,
    )->load('lines');
    $orderLine = $order->lines->firstOrFail();

    $first = app(CreateDispatchFromOrder::class)->handle(
        $order,
        [$orderLine->id => '4.000'],
        [$orderLine->id => $location->id],
        '2026-10-02',
        'faz3-race-dispatch-first-create',
    );
    $second = app(CreateDispatchFromOrder::class)->handle(
        $order,
        [$orderLine->id => '4.000'],
        [$orderLine->id => $location->id],
        '2026-10-02',
        'faz3-race-dispatch-second-create',
    );

    app(PostDispatch::class)->handle($first, 'faz3-race-dispatch-first-post');

    expect(fn () => app(PostDispatch::class)->handle(
        $second,
        'faz3-race-dispatch-second-post',
    ))->toThrow(DomainException::class, 'Belge kaynak satırın kalan miktarını aşıyor.');

    expect(app(SourceLineAvailability::class)->orderRemaining($orderLine->refresh()))->toBe('1.000')
        ->and(StockMovement::query()
            ->where('document_type', DocumentType::Dispatch->value)
            ->count())->toBe(1);
});
