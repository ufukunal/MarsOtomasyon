<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use App\Support\Concurrency\HasOptimisticLock;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConfigOption extends PeriodModel
{
    use HasOptimisticLock;

    protected $fillable = [
        'config_definition_id',
        'component_product_id',
        'label',
        'sort_order',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_default' => 'boolean',
            'version' => 'integer',
        ];
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(ConfigDefinition::class, 'config_definition_id');
    }

    public function componentProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'component_product_id');
    }
}
