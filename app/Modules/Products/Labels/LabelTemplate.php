<?php

namespace App\Modules\Products\Labels;

use Illuminate\Database\Eloquent\Model;

final class LabelTemplate extends Model
{
    protected $fillable = [
        'company_id',
        'key',
        'name',
        'target_type',
        'output_format',
        'width_mm',
        'height_mm',
        'body_template',
        'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
