<?php

namespace App\Modules\Products\Models;

use App\Modules\Core\Models\Attachment;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ProductInstallationGuideVersion extends Model
{
    protected $fillable = [
        'company_id',
        'guide_id',
        'version_no',
        'content',
        'pdf_attachment_id',
        'generated_by_user_id',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'guide_id' => 'integer',
            'version_no' => 'integer',
            'pdf_attachment_id' => 'integer',
            'generated_by_user_id' => 'integer',
            'content' => 'array',
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

    /** @return BelongsTo<ProductInstallationGuide, $this> */
    public function guide(): BelongsTo
    {
        return $this->belongsTo(ProductInstallationGuide::class, 'guide_id');
    }

    /** @return BelongsTo<Attachment, $this> */
    public function pdfAttachment(): BelongsTo
    {
        return $this->belongsTo(Attachment::class, 'pdf_attachment_id');
    }

    /** @return BelongsTo<User, $this> */
    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by_user_id');
    }
}
