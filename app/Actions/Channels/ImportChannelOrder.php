<?php

namespace App\Actions\Channels;

use App\Actions\Documents\SaveSalesDocumentDraft;
use App\Actions\Sales\ConfirmSalesOrder;
use App\Actions\Sales\ReserveSalesOrderLines;
use App\DataObjects\Channels\ChannelInboundEvent;
use App\Enums\ChannelStockMode;
use App\Enums\DocumentType;
use App\Models\Period\ChannelAccountPeriodSetting;
use App\Models\Period\ChannelOrderSnapshot;
use App\Models\Period\ChannelProductListing;
use App\Models\Period\Document;
use App\Models\Period\Product;
use App\Models\SalesChannelAccount;
use App\Support\Channels\ChannelAutomationActorResolver;
use App\Support\Period\PeriodContext;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

final class ImportChannelOrder
{
    public function __construct(
        private readonly SaveSalesDocumentDraft $saveDraft,
        private readonly ConfirmSalesOrder $confirm,
        private readonly ReserveSalesOrderLines $reserve,
        private readonly ChannelAutomationActorResolver $actors,
    ) {}

    public function handle(
        SalesChannelAccount $account,
        ChannelInboundEvent $event,
    ): Document {
        if ($event->eventType !== 'order') {
            throw new DomainException('ImportChannelOrder yalnız order event kabul eder.');
        }

        return $this->actors->run(
            ['sales_orders.create', 'sales_orders.update', 'reservations.create'],
            fn () => DB::connection('period')->transaction(
                fn (): Document => $this->import($account, $event),
                attempts: 3,
            ),
        );
    }

    private function import(
        SalesChannelAccount $account,
        ChannelInboundEvent $event,
    ): Document {
        PeriodContext::ensureWritable();
        $data = $event->data;
        $orderNumber = trim((string) ($data['orderNumber'] ?? $event->externalId));
        $externalOrderId = trim((string) ($data['externalOrderId'] ?? $orderNumber));

        if ($orderNumber === '' || $externalOrderId === '') {
            throw new DomainException('Kanal orderNumber/externalOrderId boş olamaz.');
        }

        $existingSnapshot = ChannelOrderSnapshot::query()
            ->where('channel_account_id', $account->id)
            ->where('external_order_id', $externalOrderId)
            ->lockForUpdate()
            ->first();

        if ($existingSnapshot) {
            $order = Document::query()->with('lines')->lockForUpdate()->findOrFail($existingSnapshot->sales_order_id);

            if ($order->status === 'draft') {
                $order = $this->confirm->handle(
                    $order,
                    hash('sha256', 'channel-order-confirm:'.$account->id.':'.$externalOrderId),
                    true,
                );
                $this->reserveStockLines($account, $order);
            }

            return $order->refresh();
        }

        $setting = ChannelAccountPeriodSetting::query()
            ->where('channel_account_id', $account->id)
            ->first();

        if (! $setting) {
            throw new DomainException('Kanal hesabı için dönem marketplace müşteri carisi eşlenmemiş.');
        }

        $currency = strtoupper((string) ($data['currencyCode'] ?? 'TRY'));

        if ($currency !== 'TRY') {
            throw new DomainException('Kanal siparişi ilk sürümde yalnız TRY olarak import edilebilir.');
        }

        $lines = [];
        $lineListings = [];

        foreach (($data['lines'] ?? []) as $sourceLine) {
            if (! is_array($sourceLine)) {
                continue;
            }

            $quantity = bcadd((string) ($sourceLine['quantity'] ?? '0'), '0', 3);

            if (bccomp($quantity, '0', 3) <= 0) {
                continue;
            }

            $listing = $this->resolveListing($account, $sourceLine);
            $product = Product::query()->findOrFail($listing->product_id);
            $grossUnit = bcadd((string) ($sourceLine['lineGrossAmount'] ?? $sourceLine['lineUnitPrice'] ?? '0'), '0', 4);
            $unitDiscount = bcadd((string) ($sourceLine['lineTotalDiscount'] ?? '0'), '0', 4);
            $discountAmount = bcadd(bcmul($unitDiscount, $quantity, 8), '0', 4);

            if (bccomp($grossUnit, '0', 4) < 0
                || bccomp($discountAmount, bcmul($grossUnit, $quantity, 8), 4) > 0) {
                throw new DomainException('Kanal sipariş satırı fiyat/iskonto değerleri geçersiz.');
            }

            $lineId = (string) ($sourceLine['lineId'] ?? '');
            $stockCode = (string) ($sourceLine['stockCode'] ?? '');
            $barcode = (string) ($sourceLine['barcode'] ?? '');

            $lines[] = [
                'line_kind' => 'stock',
                'product_id' => (int) $product->id,
                'unit_id' => (int) $product->unit_id,
                'quantity' => $quantity,
                'unit_price' => $grossUnit,
                'line_discount_rate' => '0',
                'line_discount_amount' => $discountAmount,
                'vat_rate' => bcadd((string) ($sourceLine['vatRate'] ?? $product->vat_rate), '0', 4),
                'reserve_stock' => false,
                'configuration' => [
                    'channel' => [
                        'platform' => $account->platform->value,
                        'channel_account_id' => (int) $account->id,
                        'external_order_id' => $externalOrderId,
                        'external_line_id' => $lineId !== '' ? $lineId : null,
                        'stock_code' => $stockCode !== '' ? $stockCode : null,
                        'barcode' => $barcode !== '' ? $barcode : null,
                        'sales_campaign_id' => $sourceLine['salesCampaignId'] ?? null,
                        'line_seller_discount' => $sourceLine['lineSellerDiscount'] ?? null,
                        'line_ty_discount' => $sourceLine['lineTyDiscount'] ?? null,
                    ],
                ],
            ];
            $lineListings[] = $listing;
        }

        if ($lines === []) {
            throw new DomainException('Kanal siparişinde import edilebilir ürün satırı bulunamadı.');
        }

        $date = $this->orderDate($data['orderDate'] ?? null);
        $draft = $this->saveDraft->handle(
            DocumentType::SalesOrder,
            [
                'document_date' => $date->toDateString(),
                'contact_id' => (int) $setting->marketplace_customer_contact_id,
                'discount_rate' => '0',
                'discount_amount' => '0',
                'requirements_snapshot' => [
                    'channel' => [
                        'platform' => $account->platform->value,
                        'channel_account_id' => (int) $account->id,
                        'external_order_id' => $externalOrderId,
                    ],
                ],
                'notes' => 'Kanal siparişi '.$account->platform->label().' '.$orderNumber,
            ],
            $lines,
        );

        $snapshot = ChannelOrderSnapshot::query()->create([
            'sales_order_id' => $draft->id,
            'channel_account_id' => $account->id,
            'external_order_id' => $externalOrderId,
        ]);
        $this->updateSnapshot($snapshot, $data);

        $confirmed = $this->confirm->handle(
            $draft,
            hash('sha256', 'channel-order-confirm:'.$account->id.':'.$externalOrderId),
            true,
        );

        $this->reserveStockLines($account, $confirmed, $lineListings);

        return $confirmed->refresh();
    }

    /** @param array<string,mixed> $sourceLine */
    private function resolveListing(
        SalesChannelAccount $account,
        array $sourceLine,
    ): ChannelProductListing {
        $stockCode = trim((string) ($sourceLine['stockCode'] ?? ''));
        $barcode = trim((string) ($sourceLine['barcode'] ?? ''));
        $externalProductId = trim((string) ($sourceLine['externalProductId'] ?? ''));

        $query = ChannelProductListing::query()
            ->where('channel_account_id', $account->id)
            ->where('is_active', true)
            ->with('product');

        $matches = collect();

        if ($externalProductId !== '') {
            $matches = (clone $query)
                ->where('external_product_id', $externalProductId)
                ->get();
        }

        if ($matches->isEmpty() && $stockCode !== '') {
            $matches = (clone $query)
                ->where(function ($builder) use ($stockCode): void {
                    $builder->where('external_sku', $stockCode)
                        ->orWhereHas('product', fn ($product) => $product->where('code', $stockCode));
                })
                ->get();
        }

        if ($matches->isEmpty() && $barcode !== '') {
            $matches = (clone $query)
                ->whereHas('product', fn ($product) => $product->where('barcode', $barcode))
                ->get();
        }

        if ($matches->count() !== 1) {
            throw new DomainException(
                'Kanal sipariş satırı için tekil ürün listing mapping bulunamadı: '.($externalProductId ?: $stockCode ?: $barcode),
            );
        }

        return $matches->first();
    }

    /**
     * @param  list<ChannelProductListing>|null  $knownListings
     */
    private function reserveStockLines(
        SalesChannelAccount $account,
        Document $order,
        ?array $knownListings = null,
    ): void {
        $order->loadMissing('lines.product');
        $priorities = [];

        foreach ($order->lines->values() as $index => $line) {
            if ($line->line_kind !== 'stock' || $line->product_id === null) {
                continue;
            }

            $listing = $knownListings[$index] ?? ChannelProductListing::query()
                ->with(['product', 'locations'])
                ->where('channel_account_id', $account->id)
                ->where('product_id', $line->product_id)
                ->first();

            if (! $listing) {
                continue;
            }

            $mode = $listing->stock_mode
                ? ChannelStockMode::from((string) $listing->stock_mode)
                : $listing->product->channel_stock_mode;

            if ($mode !== ChannelStockMode::Stock) {
                continue;
            }

            $locationIds = $listing->locations()
                ->orderBy('location_id')
                ->pluck('location_id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            if ($locationIds !== []) {
                $priorities[(int) $line->id] = $locationIds;
            }
        }

        if ($priorities !== []) {
            $this->reserve->handle(
                $order,
                $priorities,
                hash('sha256', 'channel-order-reserve:'.$account->id.':'.$order->id),
            );
        }
    }

    /** @param array<string,mixed> $data */
    private function updateSnapshot(ChannelOrderSnapshot $snapshot, array $data): void
    {
        $address = is_array($data['shipmentAddress'] ?? null) ? $data['shipmentAddress'] : [];
        $snapshot->fill([
            'external_order_no' => (string) ($data['orderNumber'] ?? $snapshot->external_order_id),
            'buyer_name' => trim((string) (($data['customerFirstName'] ?? '').' '.($data['customerLastName'] ?? ''))) ?: null,
            'recipient_name' => trim((string) ($address['fullName'] ?? '')) ?: null,
            'phone' => trim((string) ($address['phone'] ?? '')) ?: null,
            'email' => trim((string) ($data['customerEmail'] ?? '')) ?: null,
            'address' => trim((string) ($address['fullAddress'] ?? $address['address1'] ?? '')) ?: null,
            'city' => trim((string) ($address['city'] ?? '')) ?: null,
            'district' => trim((string) ($address['district'] ?? '')) ?: null,
            'postcode' => trim((string) ($address['postalCode'] ?? '')) ?: null,
            'cargo_company' => trim((string) ($data['cargoProviderName'] ?? '')) ?: null,
            'cargo_code' => trim((string) ($data['cargoTrackingNumber'] ?? '')) ?: null,
            'external_shipment_id' => trim((string) ($data['shipmentNumber'] ?? '')) ?: null,
            'external_package_id' => trim((string) ($data['shipmentPackageId'] ?? '')) ?: null,
            'campaign_metadata' => [
                'payment_method' => $data['paymentMethod'] ?? null,
                'channel_id' => $data['channelId'] ?? null,
                'discount_displays' => is_array($data['discountDisplays'] ?? null)
                    ? $data['discountDisplays']
                    : [],
                'package_seller_discount' => $data['packageSellerDiscount'] ?? null,
                'package_ty_discount' => $data['packageTyDiscount'] ?? null,
                'package_status' => $data['status'] ?? null,
                'package_last_modified_date' => $data['lastModifiedDate'] ?? null,
            ],
        ]);
        $snapshot->save();
    }

    private function orderDate(mixed $value): CarbonImmutable
    {
        if (is_numeric($value) && (int) $value > 0) {
            return CarbonImmutable::createFromTimestampMs((int) $value, config('app.timezone'));
        }

        return CarbonImmutable::now();
    }
}
