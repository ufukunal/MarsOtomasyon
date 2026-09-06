<?php

namespace App\Modules\Reports\Bi;

use Illuminate\Support\Facades\DB;

final class SalesInvoiceDataset implements BiDataset
{
    private ?string $watermark = null;

    public function key(): string
    {
        return 'sales_invoices';
    }

    public function schemaVersion(): int
    {
        return 1;
    }

    public function fields(): array
    {
        return [
            'invoice_id' => ['pii' => false],
            'number' => ['pii' => false],
            'invoice_date' => ['pii' => false],
            'currency_code' => ['pii' => false],
            'net_total' => ['pii' => false],
            'tax_total' => ['pii' => false],
            'gross_total' => ['pii' => false],
            'customer_legal_name' => ['pii' => true],
            'recipient_name' => ['pii' => true],
        ];
    }

    public function rows(int $companyId, ?string $watermark = null): iterable
    {
        $afterId = ctype_digit((string) $watermark) ? (int) $watermark : 0;
        $query = DB::table('sales_invoices')
            ->where('company_id', $companyId)
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->select([
                'id', 'company_id', 'number', 'invoice_date', 'currency_code',
                'net_total', 'tax_total', 'gross_total', 'customer_legal_name', 'recipient_name',
            ]);

        foreach ($query->cursor() as $row) {
            $this->watermark = (string) $row->id;
            yield [
                'company_id' => (int) $row->company_id,
                'invoice_id' => (int) $row->id,
                'number' => (string) $row->number,
                'invoice_date' => (string) $row->invoice_date,
                'currency_code' => (string) $row->currency_code,
                'net_total' => (string) $row->net_total,
                'tax_total' => (string) $row->tax_total,
                'gross_total' => (string) $row->gross_total,
                'customer_legal_name' => (string) $row->customer_legal_name,
                'recipient_name' => (string) $row->recipient_name,
            ];
        }
    }

    public function nextWatermark(): ?string
    {
        return $this->watermark;
    }
}
