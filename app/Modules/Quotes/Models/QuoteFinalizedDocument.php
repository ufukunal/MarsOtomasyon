<?php

namespace App\Modules\Quotes\Models;

use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\FileAsset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class QuoteFinalizedDocument extends Model
{
    protected $fillable = [
        'company_id',
        'quote_id',
        'quote_revision_id',
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
            'quote_id' => 'integer',
            'quote_revision_id' => 'integer',
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

    /** @return BelongsTo<Quote, $this> */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    /** @return BelongsTo<QuoteRevision, $this> */
    public function revision(): BelongsTo
    {
        return $this->belongsTo(QuoteRevision::class, 'quote_revision_id');
    }

    /** @return BelongsTo<FileAsset, $this> */
    public function fileAsset(): BelongsTo
    {
        return $this->belongsTo(FileAsset::class);
    }
}
