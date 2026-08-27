<?php

namespace App\Modules\SalesInvoices\Models;

use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\FileAsset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SalesInvoiceFinalizedDocument extends Model
{
    protected $fillable = [
        'company_id',
        'sales_invoice_id',
        'file_asset_id',
        'renderer_version',
        'source_fingerprint',
        'pdf_sha256',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'sales_invoice_id' => 'integer',
            'file_asset_id' => 'integer',
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

    /** @return BelongsTo<SalesInvoice, $this> */
    public function salesInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class);
    }

    /** @return BelongsTo<FileAsset, $this> */
    public function fileAsset(): BelongsTo
    {
        return $this->belongsTo(FileAsset::class);
    }
}
