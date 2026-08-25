<?php

namespace App\Modules\SalesOrders\Actions;

use App\Modules\SalesOrders\Models\SalesOrder;
use App\Modules\SalesOrders\Models\SalesOrderLine;
use LogicException;

final readonly class SalesOrderAuditSnapshot
{
    /** @return array<string, mixed> */
    public function capture(SalesOrder $order): array
    {
        $order->unsetRelation('lines');
        $order->load('lines');

        return [
            'number' => $this->rawString($order, 'number'),
            'series_code' => $this->rawString($order, 'series_code'),
            'sequence_value' => (int) $order->sequence_value,
            'account_id' => (int) $order->account_id,
            'status' => $order->statusEnum()->value,
            'order_date' => $this->rawString($order, 'order_date'),
            'currency_code' => $this->rawString($order, 'currency_code'),
            'document_discount_rate' => $this->rawString($order, 'document_discount_rate'),
            'base_net_total' => $this->rawString($order, 'base_net_total'),
            'line_discount_total' => $this->rawString($order, 'line_discount_total'),
            'document_discount_total' => $this->rawString($order, 'document_discount_total'),
            'net_total' => $this->rawString($order, 'net_total'),
            'tax_total' => $this->rawString($order, 'tax_total'),
            'gross_total' => $this->rawString($order, 'gross_total'),
            'note' => $order->note === null ? null : (string) $order->note,
            'source_quote_id' => $order->getRawOriginal('source_quote_id') === null ? null : (int) $order->source_quote_id,
            'source_quote_revision_id' => $order->getRawOriginal('source_quote_revision_id') === null ? null : (int) $order->source_quote_revision_id,
            'lines' => $order->lines->map(fn (SalesOrderLine $line): array => [
                'position' => (int) $line->position,
                'product_id' => (int) $line->product_id,
                'product_code' => $this->rawString($line, 'product_code'),
                'product_name' => $this->rawString($line, 'product_name'),
                'description' => $this->rawString($line, 'description'),
                'quantity' => $this->rawString($line, 'quantity'),
                'price_basis' => $this->rawString($line, 'price_basis'),
                'unit_price' => $this->rawString($line, 'unit_price'),
                'line_discount_rate' => $this->rawString($line, 'line_discount_rate'),
                'tax_id' => (int) $line->tax_id,
                'tax_code' => $this->rawString($line, 'tax_code'),
                'tax_rate' => $this->rawString($line, 'tax_rate'),
                'tax_zero_reason_id' => $line->getRawOriginal('tax_zero_reason_id') === null ? null : (int) $line->tax_zero_reason_id,
                'tax_zero_reason_code' => $line->getRawOriginal('tax_zero_reason_code') === null ? null : (string) $line->tax_zero_reason_code,
                'base_net' => $this->rawString($line, 'base_net'),
                'line_discount_net' => $this->rawString($line, 'line_discount_net'),
                'document_discount_net' => $this->rawString($line, 'document_discount_net'),
                'net_total' => $this->rawString($line, 'net_total'),
                'tax_total' => $this->rawString($line, 'tax_total'),
                'gross_total' => $this->rawString($line, 'gross_total'),
            ])->values()->all(),
        ];
    }

    private function rawString(SalesOrder|SalesOrderLine $model, string $attribute): string
    {
        $raw = $model->getRawOriginal($attribute);
        if (! is_string($raw) && ! is_int($raw)) {
            throw new LogicException('Persisted '.$attribute.' must be scalar.');
        }

        return (string) $raw;
    }
}
