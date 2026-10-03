<?php

namespace App\Models\Period;

use App\Contracts\HasAttachments as HasAttachmentsContract;
use App\Contracts\SearchIndexed;
use App\Enums\ContactType;
use App\Models\Concerns\HasAttachments;
use App\Models\PeriodModel;
use App\Support\Concurrency\HasOptimisticLock;
use App\Support\Search\HasSearchIndex;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property ContactType $type
 */
class Contact extends PeriodModel implements HasAttachmentsContract, SearchIndexed
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
        static::creating(function (self $contact): void {
            if ($contact->getAttribute('code')) {
                return;
            }

            $row = DB::connection('period')
                ->selectOne("SELECT nextval('contact_code_seq')::bigint AS value");

            $next = (int) ($row?->value ?? 0);

            if ($next <= 0) {
                throw new LogicException('Cari kod sırası üretilemedi.');
            }

            $contact->setAttribute(
                'code',
                'CR'.str_pad((string) $next, 7, '0', STR_PAD_LEFT),
            );
        });

        static::updating(function (self $contact): void {
            if ($contact->isDirty('code')) {
                throw new LogicException('Cari kodu kayıt sonrası değiştirilemez.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Cari kartı fiziksel olarak silinemez; pasife alınmalıdır.');
        });
    }

    public function searchableFields(): array
    {
        return ['code', 'title', 'tax_number', 'phone', 'email', 'city'];
    }

    /** @return BelongsToMany<ContactCategory, $this> */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            ContactCategory::class,
            'contact_category',
            'contact_id',
            'contact_category_id',
        );
    }

    /** @return HasMany<ContactAddress, $this> */
    public function addresses(): HasMany
    {
        return $this->hasMany(ContactAddress::class);
    }

    /** @return HasMany<ContactPerson, $this> */
    public function people(): HasMany
    {
        return $this->hasMany(ContactPerson::class);
    }

    /** @return HasMany<ContactBank, $this> */
    public function banks(): HasMany
    {
        return $this->hasMany(ContactBank::class);
    }

    /** @return BelongsTo<PriceList, $this> */
    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function balance(): string
    {
        if (! Schema::connection('period')->hasTable('contact_transactions')) {
            return '0.0000';
        }

        $balance = $this->newQuery()
            ->getConnection()
            ->table('contact_transactions')
            ->where('contact_id', $this->id)
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'debit' THEN amount ELSE -amount END), 0)::text AS balance")
            ->value('balance');

        return bcadd((string) ($balance ?? '0'), '0', 4);
    }

    public function getPrimaryContactNameAttribute(): string
    {
        $people = $this->relationLoaded('people')
            ? $this->people
            : $this->people()->get();

        $primary = $people->firstWhere('is_default', true);

        if ($primary) {
            return (string) $primary->name;
        }

        $first = $people->first();

        return $first ? (string) $first->name : '';
    }

    public function getCategoryNamesAttribute(): string
    {
        $categories = $this->relationLoaded('categories')
            ? $this->categories
            : $this->categories()->get();

        return $categories->pluck('name')->implode(', ');
    }

    public function getBalanceDisplayAttribute(): string
    {
        return $this->balance();
    }

    public function getRiskStatusAttribute(): string
    {
        if (bccomp((string) $this->risk_limit, '0', 4) <= 0) {
            return 'Limitsiz';
        }

        return bccomp($this->balance(), (string) $this->risk_limit, 4) > 0
            ? 'Limit Aşıldı'
            : 'Normal';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('contact')
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
