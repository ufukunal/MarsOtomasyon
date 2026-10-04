<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use App\Support\Concurrency\HasOptimisticLock;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class ImportFile extends PeriodModel
{
    use HasOptimisticLock;

    protected $fillable = [
        'number', 'supplier_contact_id', 'receiving_location_id', 'country', 'incoterm',
        'currency', 'exchange_rate', 'exchange_rate_locked_at', 'exchange_rate_date',
        'etd', 'eta', 'received_at', 'status', 'notes', 'version',
        'created_by', 'created_by_name', 'closed_by', 'closed_by_name', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'supplier_contact_id' => 'integer',
            'receiving_location_id' => 'integer',
            'exchange_rate' => 'decimal:6',
            'exchange_rate_locked_at' => 'datetime',
            'exchange_rate_date' => 'date',
            'etd' => 'date',
            'eta' => 'date',
            'received_at' => 'date',
            'closed_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $file): void {
            if ($file->getOriginal('status') === 'closed') {
                throw new LogicException('Kapanmış ithalat dosyası değiştirilemez.');
            }

            if ($file->getOriginal('exchange_rate_locked_at') !== null
                && ($file->isDirty('exchange_rate') || $file->isDirty('exchange_rate_date'))) {
                throw new LogicException('Sabitlenmiş ithalat kuru değiştirilemez.');
            }
        });

        static::deleting(fn (): never => throw new LogicException('İthalat dosyası fiziksel olarak silinemez.'));
    }

    public function isLocked(): bool
    {
        return in_array($this->status, ['received', 'closed'], true);
    }

    /** @return BelongsTo<Contact, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'supplier_contact_id');
    }

    /** @return BelongsTo<Location, $this> */
    public function receivingLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'receiving_location_id');
    }

    /** @return HasMany<ImportContainer, $this> */
    public function containers(): HasMany
    {
        return $this->hasMany(ImportContainer::class);
    }

    /** @return HasMany<ImportPackage, $this> */
    public function packages(): HasMany
    {
        return $this->hasMany(ImportPackage::class);
    }

    /** @return HasMany<ImportCostItem, $this> */
    public function costItems(): HasMany
    {
        return $this->hasMany(ImportCostItem::class);
    }
}
