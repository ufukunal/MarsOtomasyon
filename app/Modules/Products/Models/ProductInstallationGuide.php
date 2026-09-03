<?php

namespace App\Modules\Products\Models;

use App\Modules\Core\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property list<string> $steps
 * @property list<string> $warnings
 * @property list<string> $tools
 * @property list<string> $parts
 * @property list<int> $image_product_file_ids
 */
final class ProductInstallationGuide extends Model
{
    protected $fillable = [
        'company_id', 'product_id', 'title', 'intro', 'steps', 'warnings', 'tools', 'parts',
        'image_product_file_ids', 'content_revision',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'product_id' => 'integer',
            'steps' => 'array',
            'warnings' => 'array',
            'tools' => 'array',
            'parts' => 'array',
            'image_product_file_ids' => 'array',
            'content_revision' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }

    /** @return HasMany<ProductInstallationDocument, $this> */
    public function documents(): HasMany { return $this->hasMany(ProductInstallationDocument::class, 'guide_id'); }
}