<?php

namespace App\Modules\Products\Labels\Models;

use App\Modules\Core\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PrinterProfile extends Model
{
    protected $fillable = [
        'company_id', 'code', 'name', 'driver', 'width_mm', 'height_mm', 'dpi', 'config', 'is_active',
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

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function prints(): HasMany
    {
        return $this->hasMany(LabelPrint::class);
    }
}
