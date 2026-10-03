<?php

namespace App\Models;

use Database\Factories\CompanyFactory;
use App\Support\Concurrency\HasOptimisticLock;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;
use LogicException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Company extends MasterModel
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;
    use HasOptimisticLock;
    use LogsActivity;

    protected $fillable = [
        'code',
        'name',
        'legal_name',
        'db_prefix',
        'tax_office',
        'tax_number',
        'address',
        'city',
        'phone',
        'email',
        'logo_path',
        'default_term_days',
        'cost_deviation_threshold',
        'base_currency',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_term_days' => 'integer',
            'cost_deviation_threshold' => 'decimal:4',
            'is_active' => 'boolean',
            'version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $company): void {
            if (! preg_match('/^[A-Za-z0-9_]+$/D', (string) $company->db_prefix)) {
                throw new InvalidArgumentException('db_prefix yalnız harf, rakam ve alt çizgi içerebilir.');
            }

            if ($company->exists && $company->isDirty('db_prefix')) {
                throw new LogicException('db_prefix kayıt oluşturulduktan sonra değiştirilemez.');
            }
        });
    }

    /** @return HasMany<Period, $this> */
    public function periods(): HasMany
    {
        return $this->hasMany(Period::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('master')
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
