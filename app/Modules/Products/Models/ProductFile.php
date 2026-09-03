<?php

namespace App\Modules\Products\Models;

use App\Modules\Core\Models\Attachment;
use App\Modules\Core\Models\Company;
use App\Modules\Products\Enums\ProductFileKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property list<string>|null $destinations
 * @property array<string, mixed>|null $transform_metadata
 * @property array<string, mixed>|null $provider_validation
 */
final class ProductFile extends Model
{
    protected $fillable = [
        'company_id',
        'product_id',
        'attachment_id',
        'kind',
        'position',
        'is_main',
        'destinations',
        'transform_metadata',
        'provider_validation',
    ];

    protected function casts(): array
    {
        return [
            'kind' => ProductFileKind::class,
            'position' => 'integer',
            'is_main' => 'boolean',
            'destinations' => 'array',
            'transform_metadata' => 'array',
            'provider_validation' => 'array',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Attachment, $this> */
    public function attachment(): BelongsTo
    {
        return $this->belongsTo(Attachment::class);
    }
}
