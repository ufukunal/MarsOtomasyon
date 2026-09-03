<?php

namespace App\Modules\Products\Models;

use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\FileAsset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property array<string, mixed> $snapshot */
final class ProductInstallationDocument extends Model
{
    protected $fillable = [
        'company_id', 'product_id', 'guide_id', 'file_asset_id', 'version', 'renderer_version',
        'snapshot', 'source_fingerprint', 'pdf_sha256', 'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'product_id' => 'integer',
            'guide_id' => 'integer',
            'file_asset_id' => 'integer',
            'version' => 'integer',
            'snapshot' => 'array',
            'generated_at' => 'immutable_datetime',
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

    /** @return BelongsTo<ProductInstallationGuide, $this> */
    public function guide(): BelongsTo
    {
        return $this->belongsTo(ProductInstallationGuide::class, 'guide_id');
    }

    /** @return BelongsTo<FileAsset, $this> */
    public function fileAsset(): BelongsTo
    {
        return $this->belongsTo(FileAsset::class);
    }
}
