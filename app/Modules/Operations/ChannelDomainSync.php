<?php

namespace App\Modules\Operations;

use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\TaxZeroReason;
use App\Modules\Products\Models\Product;
use App\Modules\Quotes\Pricing\PriceBasis;
use App\Modules\SalesOrders\Actions\CreateSalesOrder;
use App\Modules\SalesOrders\Actions\SalesOrderDraftData;
use App\Modules\SalesOrders\Actions\SalesOrderLineData;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;
use RuntimeException;

final readonly class ChannelDomainSync
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private CreateSalesOrder $createSalesOrder,
    ) {}

    /**
     * @param  object{provider:mixed,credentials_ciphertext?:mixed}  $connection
     * @param  object{event_type:mixed,company_id:mixed,connection_id:mixed,external_event_id:mixed,payload_sha256:mixed}  $event
     * @param  array<string,mixed>  $payload
     * @return array{entity_type:string,local_type:string,local_id:int,external_id:string}|null
     */
    public function ingest(object $connection, object $event, array $payload): ?array
    {
        $eventType = (string) $event->event_type;
        if (! str_contains($eventType, 'order')) {
            return null;
        }

        $companyId = (int) $event->company_id;
        $connectionId = (int) $event->connection_id;
        $provider = (string) $connection->provider;
        $externalId = $this->externalOrderId($payload, (string) $event->external_event_id);
        $payloadHash = (string) $event->payload_sha256;
        $lockKey = sprintf('mars:m11:channel:%d:%d:order:%s', $companyId, $connectionId, $externalId);

        return DB::transaction(function () use ($companyId, $connectionId, $provider, $externalId, $payloadHash, $payload, $connection, $lockKey): array {
            DB::selectOne('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [$lockKey]);

            $existing = DB::table('integration_entity_links')
                ->where('company_id', $companyId)
                ->where('connection_id', $connectionId)
                ->where('entity_type', 'order')
                ->where('external_id', $externalId)
                ->lockForUpdate()
                ->first();
            if ($existing !== null) {
                DB::table('integration_entity_links')->where('id', $existing->id)->update([
                    'last_payload_sha256' => $payloadHash,
                    'last_synced_at' => now(),
                    'updated_at' => now(),
                ]);

                return [
                    'entity_type' => 'order',
                    'local_type' => (string) $existing->local_type,
                    'local_id' => (int) $existing->local_id,
                    'external_id' => $externalId,
                ];
            }

            $credentials = $this->credentials((string) ($connection->credentials_ciphertext ?? ''));
            $accountId = (int) ($credentials['default_account_id'] ?? 0);
            if ($accountId < 1) {
                throw new DomainException('Inbound channel orders require credentials.default_account_id.');
            }

            $company = Company::query()->whereKey($companyId)->first();
            if (! $company instanceof Company) {
                throw new RuntimeException('Integration company not found.');
            }

            $previousCompany = $this->companyContext->company();
            $this->companyContext->set($company);
            try {
                $draft = $this->salesOrderDraft($companyId, $provider, $externalId, $accountId, $credentials, $payload, $company);
                $order = $this->createSalesOrder->handle($draft, (string) ($credentials['order_series'] ?? 'default'));
            } finally {
                if ($previousCompany instanceof Company) {
                    $this->companyContext->set($previousCompany);
                } else {
                    $this->companyContext->clear();
                }
            }

            $localId = (int) $order->getKey();
            DB::table('integration_entity_links')->insert([
                'company_id' => $companyId,
                'connection_id' => $connectionId,
                'entity_type' => 'order',
                'external_id' => $externalId,
                'local_type' => 'sales_order',
                'local_id' => $localId,
                'last_payload_sha256' => $payloadHash,
                'last_synced_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return [
                'entity_type' => 'order',
                'local_type' => 'sales_order',
                'local_id' => $localId,
                'external_id' => $externalId,
            ];
        }, 3);
    }

    /**
     * @param  array<string,mixed>  $credentials
     * @param  array<string,mixed>  $payload
     */
    private function salesOrderDraft(
        int $companyId,
        string $provider,
        string $externalId,
        int $accountId,
        array $credentials,
        array $payload,
        Company $company,
    ): SalesOrderDraftData {
        $rawLines = $provider === 'woocommerce' ? ($payload['line_items'] ?? []) : ($payload['lines'] ?? []);
        if (! is_array($rawLines) || $rawLines === [] || count($rawLines) > 200) {
            throw new DomainException('Inbound channel order must contain 1 to 200 lines.');
        }

        $basis = strtolower((string) ($credentials['price_basis'] ?? 'net')) === 'gross'
            ? PriceBasis::Gross
            : PriceBasis::Net;
        $lines = [];
        foreach (array_values($rawLines) as $index => $rawLine) {
            if (! is_array($rawLine)) {
                throw new DomainException('Inbound channel order line is invalid.');
            }
            $sku = $this->firstString($rawLine, ['sku', 'merchantSku', 'merchant_sku', 'barcode']);
            if ($sku === '') {
                throw new DomainException('Inbound channel order line requires a SKU/barcode mapping key.');
            }
            $product = Product::query()
                ->with('tax')
                ->where('company_id', $companyId)
                ->where('status', 'active')
                ->where(function ($query) use ($sku): void {
                    $query->where('code', $sku)
                        ->orWhereHas('barcodes', fn ($barcodeQuery) => $barcodeQuery->where('barcode', $sku));
                })
                ->first();
            if (! $product instanceof Product || $product->tax === null) {
                throw new DomainException('Channel SKU could not be mapped to an active Mars product: '.$sku);
            }

            $quantity = $this->positiveDecimal($rawLine['quantity'] ?? 0, 'quantity');
            $unitPrice = $this->unitPrice($rawLine, $quantity);
            $zeroReasonId = null;
            if ((float) $product->tax->rate === 0.0) {
                $zeroReasonId = TaxZeroReason::query()
                    ->where('company_id', $companyId)
                    ->where('is_active', true)
                    ->orderBy('id')
                    ->value('id');
                if (! is_int($zeroReasonId)) {
                    throw new DomainException('Zero-rated channel product requires an active tax zero reason.');
                }
            }

            $lines[] = new SalesOrderLineData(
                productId: (int) $product->getKey(),
                quantity: $quantity,
                unitPrice: $unitPrice,
                priceBasis: $basis,
                lineDiscountRate: '0',
                taxZeroReasonId: $zeroReasonId,
                description: $this->firstString($rawLine, ['name', 'productName', 'description']) ?: (string) $product->name,
                logicalLineKey: Uuid::uuid5(Uuid::NAMESPACE_URL, 'mars:channel:'.$provider.':'.$externalId.':'.$index.':'.$sku)->toString(),
                taxIsZeroed: false,
            );
        }

        $currency = mb_strtoupper($this->firstString($payload, ['currency', 'currencyCode', 'currency_code']));
        if ($currency === '') {
            $currency = (string) $company->base_currency_code;
        }

        return new SalesOrderDraftData(
            accountId: $accountId,
            orderDate: $this->orderDate($payload),
            currencyCode: $currency,
            documentDiscountRate: '0',
            note: sprintf('%s kanal siparişi %s', ucfirst($provider), $externalId),
            lines: $lines,
        );
    }

    /** @param array<string,mixed> $payload */
    private function externalOrderId(array $payload, string $fallback): string
    {
        $id = $this->firstString($payload, ['id', 'orderNumber', 'order_number', 'shipmentPackageId']);
        $id = $id === '' ? trim($fallback) : $id;
        if ($id === '' || mb_strlen($id) > 192) {
            throw new DomainException('Inbound channel order external id is invalid.');
        }

        return $id;
    }

    /** @param array<string,mixed> $payload */
    private function orderDate(array $payload): string
    {
        $raw = $payload['date_created'] ?? $payload['orderDate'] ?? $payload['order_date'] ?? null;
        if (is_numeric($raw)) {
            $timestamp = (int) $raw;
            if ($timestamp > 10_000_000_000) {
                $timestamp = intdiv($timestamp, 1000);
            }

            return CarbonImmutable::createFromTimestampUTC($timestamp)->toDateString();
        }
        if (is_string($raw) && trim($raw) !== '') {
            try {
                return CarbonImmutable::parse($raw)->toDateString();
            } catch (\Throwable) {
                throw new DomainException('Inbound channel order date is invalid.');
            }
        }

        return now()->toDateString();
    }

    /** @param array<string,mixed> $line */
    private function unitPrice(array $line, string $quantity): string
    {
        foreach (['price', 'salePrice', 'unitPrice', 'unit_price'] as $key) {
            if (isset($line[$key]) && is_numeric($line[$key])) {
                return $this->nonNegativeDecimal($line[$key], 'unit price');
            }
        }
        $total = $line['total'] ?? $line['amount'] ?? null;
        if (! is_numeric($total)) {
            throw new DomainException('Inbound channel order line requires unit price or total.');
        }
        $row = DB::selectOne(
            'SELECT CAST((CAST(? AS numeric) / CAST(? AS numeric)) AS numeric(20,6))::text AS value',
            [(string) $total, $quantity],
        );
        if ($row === null) {
            throw new DomainException('Inbound channel order unit price could not be calculated.');
        }

        return (string) $row->value;
    }

    private function positiveDecimal(mixed $value, string $field): string
    {
        $normalized = $this->nonNegativeDecimal($value, $field);
        $row = DB::selectOne('SELECT CAST(? AS numeric) > 0 AS valid', [$normalized]);
        if ($row?->valid !== true) {
            throw new DomainException('Inbound channel order '.$field.' must be positive.');
        }

        return $normalized;
    }

    private function nonNegativeDecimal(mixed $value, string $field): string
    {
        if (! is_numeric($value)) {
            throw new DomainException('Inbound channel order '.$field.' is invalid.');
        }
        $row = DB::selectOne('SELECT CAST(CAST(? AS numeric) AS numeric(20,6))::text AS value, CAST(? AS numeric) >= 0 AS valid', [(string) $value, (string) $value]);
        if ($row === null || $row->valid !== true) {
            throw new DomainException('Inbound channel order '.$field.' is invalid.');
        }

        return (string) $row->value;
    }

    /**
     * @param  array<string,mixed>  $values
     * @param  list<string>  $keys
     */
    private function firstString(array $values, array $keys): string
    {
        foreach ($keys as $key) {
            $value = $values[$key] ?? null;
            if (is_string($value) || is_int($value) || is_float($value)) {
                $text = trim((string) $value);
                if ($text !== '') {
                    return $text;
                }
            }
        }

        return '';
    }

    /** @return array<string,mixed> */
    private function credentials(string $ciphertext): array
    {
        if ($ciphertext === '') {
            return [];
        }
        $decoded = json_decode(Crypt::decryptString($ciphertext), true, flags: JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : [];
    }
}
