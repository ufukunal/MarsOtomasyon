<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use App\Support\Concurrency\HasOptimisticLock;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $number
 * @property string $currency
 * @property string|null $exchange_rate
 * @property Carbon|null $exchange_rate_locked_at
 * @property Carbon|null $exchange_rate_date
 * @property Carbon|null $etd
 * @property Carbon|null $eta
 * @property Carbon|null $received_at
 * @property Carbon|null $closed_at
 * @property string $status
 */
class ImportFile extends PeriodModel
{
    use HasOptimisticLock;

    protected $fillable = [
        'number', 'source_period_id', 'source_import_file_id', 'source_number',
        'supplier_contact_id', 'receiving_location_id', 'country', 'incoterm',
        'currency', 'exchange_rate', 'exchange_rate_locked_at', 'exchange_rate_date',
        'etd', 'eta', 'received_at', 'status', 'notes', 'version',
        'created_by', 'created_by_name', 'closed_by', 'closed_by_name', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'source_period_id' => 'integer',
            'source_import_file_id' => 'integer',
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
            $originalStatus = (string) $file->getOriginal('status');

            if ($file->getOriginal('source_period_id') !== null
                && ($file->isDirty('source_period_id')
                    || $file->isDirty('source_import_file_id')
                    || $file->isDirty('source_number'))) {
                throw new LogicException('İthalat dosyasının dönem devir kaynağı değiştirilemez.');
            }

            if ($originalStatus === 'closed') {
                throw new LogicException('Kapanmış ithalat dosyası değiştirilemez.');
            }

            if ($originalStatus === 'received') {
                $allowed = ['status', 'closed_by', 'closed_by_name', 'closed_at', 'version', 'updated_at'];

                foreach (array_keys($file->getDirty()) as $field) {
                    if (! in_array($field, $allowed, true)) {
                        throw new LogicException('Teslim alınmış ithalat dosyasında yalnız kapanış alanları değişebilir.');
                    }
                }
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
