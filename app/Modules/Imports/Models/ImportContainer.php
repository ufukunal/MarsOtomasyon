<?php

namespace App\Modules\Imports\Models;

use Illuminate\Database\Eloquent\Model;

final class ImportContainer extends Model
{
    protected $fillable = ['company_id', 'import_file_id', 'container_no', 'seal_no', 'container_type', 'max_weight_kg', 'max_volume_m3', 'note'];

    protected function casts(): array
    {
        return ['max_weight_kg' => 'decimal:6', 'max_volume_m3' => 'decimal:6', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }
}
