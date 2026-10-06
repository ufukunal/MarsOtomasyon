<?php

namespace App\Models;

use App\Enums\PrintType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $company_id
 * @property int|null $user_id
 * @property PrintType $print_type
 * @property int|null $template_id
 * @property int|null $template_revision_no
 * @property int|null $profile_id
 * @property string|null $source_type
 * @property int|null $source_id
 * @property int $quantity
 * @property string $status
 * @property array<string, mixed>|null $result_metadata
 */
class PrintJob extends MasterModel
{
    protected $fillable = [
        'company_id',
        'user_id',
        'machine_key',
        'print_type',
        'template_id',
        'template_revision_no',
        'profile_id',
        'source_type',
        'source_id',
        'quantity',
        'status',
        'result_metadata',
        'parameters_hash',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'user_id' => 'integer',
            'print_type' => PrintType::class,
            'template_id' => 'integer',
            'template_revision_no' => 'integer',
            'profile_id' => 'integer',
            'source_id' => 'integer',
            'quantity' => 'integer',
            'result_metadata' => 'array',
        ];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<DocumentTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'template_id');
    }

    /** @return BelongsTo<PrintProfile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(PrintProfile::class, 'profile_id');
    }
}
