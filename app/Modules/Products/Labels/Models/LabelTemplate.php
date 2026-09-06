<?php

namespace App\Modules\Products\Labels\Models;

use App\Modules\Core\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class LabelTemplate extends Model
{
    protected $fillable = [
        'company_id', 'code', 'name', 'target_type', 'format', 'width_mm', 'height_mm',
        'dpi', 'body', 'config', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'width_mm' => 'decimal:2',
            'height_mm' => 'decimal:2',
            'dpi' => 'integer',
            'config' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return HasMany<LabelPrint, $this> */
    public function prints(): HasMany
    {
        return $this->hasMany(LabelPrint::class);
    }
}
