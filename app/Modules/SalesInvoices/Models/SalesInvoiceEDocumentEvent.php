<?php

namespace App\Modules\SalesInvoices\Models;

use App\Modules\Core\Models\Company;
use App\Modules\SalesInvoices\Enums\SalesInvoiceEDocumentEventType;
use App\Modules\SalesInvoices\Enums\SalesInvoiceEDocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class SalesInvoiceEDocumentEvent extends Model
{
    protected $fillable = [
        'company_id',
        'sales_invoice_id',
        'document_type',
        'event_type',
        'provider_key',
        'external_document_id',
        'payload_sha256',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'sales_invoice_id' => 'integer',
            'document_type' => SalesInvoiceEDocumentType::class,
            'event_type' => SalesInvoiceEDocumentEventType::class,
            'occurred_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function documentTypeEnum(): SalesInvoiceEDocumentType
    {
        $raw = $this->getRawOriginal('document_type');

        return is_string($raw) && SalesInvoiceEDocumentType::tryFrom($raw) instanceof SalesInvoiceEDocumentType
            ? SalesInvoiceEDocumentType::from($raw)
            : throw new LogicException('Persisted e-document type is invalid.');
    }

    public function eventTypeEnum(): SalesInvoiceEDocumentEventType
    {
        $raw = $this->getRawOriginal('event_type');

        return is_string($raw) && SalesInvoiceEDocumentEventType::tryFrom($raw) instanceof SalesInvoiceEDocumentEventType
            ? SalesInvoiceEDocumentEventType::from($raw)
            : throw new LogicException('Persisted e-document event type is invalid.');
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<SalesInvoice, $this> */
    public function salesInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class);
    }
}
