<?php

namespace App\Models;

use App\Enums\PrintType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'template_id');
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(PrintProfile::class, 'profile_id');
    }
}
