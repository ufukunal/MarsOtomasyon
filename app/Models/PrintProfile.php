<?php

namespace App\Models;

use App\Enums\PrintType;
use App\Models\Concerns\HasOptimisticLock;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrintProfile extends MasterModel
{
    use HasOptimisticLock;

    protected $fillable = [
        'company_id',
        'user_id',
        'machine_key',
        'print_type',
        'printer_name',
        'paper_code',
        'width_mm',
        'height_mm',
        'settings',
        'template_id',
    ];

    protected function casts(): array
    {
        return [
            'print_type' => PrintType::class,
            'width_mm' => 'decimal:2',
            'height_mm' => 'decimal:2',
            'settings' => 'array',
            'version' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
