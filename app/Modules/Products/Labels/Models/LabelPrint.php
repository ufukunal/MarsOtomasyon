<?php

namespace App\Modules\Products\Labels\Models;

use App\Models\User;
use App\Modules\Core\Models\Company;
use App\Modules\Products\Models\Barcode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class LabelPrint extends Model
{
    protected $fillable = [
        'company_id', 'label_template_id', 'printer_profile_id', 'target_type', 'target_id',
        'barcode_id', 'format', 'payload_snapshot', 'template_snapshot', 'printer_snapshot',
        'output_base64', 'content_hash', 'reprint_of_id', 'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'target_id' => 'integer',
            'payload_snapshot' => 'array',
            'template_snapshot' => 'array',
            'printer_snapshot' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(LabelTemplate::class, 'label_template_id');
    }

    public function printerProfile(): BelongsTo
    {
        return $this->belongsTo(PrinterProfile::class);
    }

    public function barcode(): BelongsTo
    {
        return $this->belongsTo(Barcode::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function original(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reprint_of_id');
    }

    public function reprints(): HasMany
    {
        return $this->hasMany(self::class, 'reprint_of_id');
    }
}
