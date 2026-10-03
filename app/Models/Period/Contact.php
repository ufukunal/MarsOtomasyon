<?php

namespace App\Models\Period;

use App\Enums\ContactType;
use App\Models\Concerns\HasAttachments;
use App\Models\PeriodModel;
use App\Support\Concurrency\HasOptimisticLock;
use App\Support\Search\HasSearchIndex;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Contact extends PeriodModel
{
    use HasAttachments;
    use HasOptimisticLock;
    use HasSearchIndex;
    use LogsActivity;

    protected $fillable = [
        'title',
        'type',
        'tax_office',
        'tax_number',
        'national_id',
        'address',
        'city',
        'district',
        'phone',
        'email',
        'term_days',
        'risk_limit',
        'discount_rate',
        'price_list_id',
        'source_company_id',
        'source_record_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => ContactType::class,
            'term_days' => 'integer',
            'risk_limit' => 'decimal:4',
            'discount_rate' => 'decimal:4',
            'is_active' => 'boolean',
            'version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $contact): void {
            if ($contact->isDirty('code')) {
                throw new LogicException('Cari kodu kayıt sonrası değiştirilemez.');
            }
        });
    }

    public function searchableFields(): array
    {
        return ['code', 'title', 'tax_number', 'phone', 'email', 'city'];
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            ContactCategory::class,
            'contact_category',
            'contact_id',
            'contact_category_id',
        );
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(ContactAddress::class);
    }

    public function people(): HasMany
    {
        return $this->hasMany(ContactPerson::class);
    }

    public function banks(): HasMany
    {
        return $this->hasMany(ContactBank::class);
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function balance(): string
    {
        return '0.0000';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('contact')
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
