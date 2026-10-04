<?php

namespace App\Models\Period;

use App\Enums\DocumentType;
use App\Models\PeriodModel;
use App\Support\Concurrency\HasOptimisticLock;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property DocumentType $document_type
 * @property Carbon $document_date
 * @property Carbon|null $due_date
 * @property Carbon|null $valid_until
 * @property Carbon|null $posted_at
 * @property int|null $contact_id
 * @property string $status
 * @property string $exchange_rate
 * @property string $discount_rate
 * @property string $discount_amount
 * @property string $subtotal
 * @property string $tax_base
 * @property string $vat_amount
 * @property string $rounding_difference
 * @property string $grand_total
 * @property array<string, mixed>|null $requirements_snapshot
 */
class Document extends PeriodModel
{
    use HasOptimisticLock;

    protected $fillable = [
        'document_type', 'number', 'revision_no', 'document_date', 'due_date', 'valid_until',
        'contact_id', 'currency', 'exchange_rate', 'status', 'discount_rate', 'discount_amount',
        'subtotal', 'tax_base', 'vat_amount', 'rounding_difference', 'grand_total',
        'requirements_snapshot', 'notes', 'version', 'created_by', 'created_by_name',
        'posted_by', 'posted_by_name', 'posted_at',
    ];

    protected static function booted(): void
    {
        static::updating(function (Document $document): void {
            if ($document->getOriginal('status') === 'posted') {
                throw new LogicException('Kesinleşmiş belge yerinde değiştirilemez.');
            }
        });

        static::deleting(function (Document $document): void {
            if ($document->status !== 'draft') {
                throw new LogicException('Yalnız taslak belge silinebilir.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'document_type' => DocumentType::class,
            'revision_no' => 'integer',
            'document_date' => 'date',
            'due_date' => 'date',
            'valid_until' => 'date',
            'contact_id' => 'integer',
            'exchange_rate' => 'decimal:6',
            'discount_rate' => 'decimal:4',
            'discount_amount' => 'decimal:4',
            'subtotal' => 'decimal:4',
            'tax_base' => 'decimal:4',
            'vat_amount' => 'decimal:4',
            'rounding_difference' => 'decimal:4',
            'grand_total' => 'decimal:4',
            'requirements_snapshot' => 'array',
            'version' => 'integer',
            'created_by' => 'integer',
            'posted_by' => 'integer',
            'posted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** @return HasMany<DocumentLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(DocumentLine::class)->orderBy('line_no');
    }

    /** @return HasMany<DocumentRelation, $this> */
    public function outgoingRelations(): HasMany
    {
        return $this->hasMany(DocumentRelation::class, 'source_document_id');
    }

    /** @return HasMany<DocumentRelation, $this> */
    public function incomingRelations(): HasMany
    {
        return $this->hasMany(DocumentRelation::class, 'target_document_id');
    }

    /** @return HasMany<ContactTransaction, $this> */
    public function contactTransactions(): HasMany
    {
        return $this->hasMany(ContactTransaction::class);
    }
}
