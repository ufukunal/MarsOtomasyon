<?php

namespace App\Modules\GoodsReceipts\Models;

use App\Modules\Core\Models\User;
use App\Modules\GoodsReceipts\Enums\GoodsReceiptQualityDisposition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class GoodsReceiptQualityEffect extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'company_id', 'goods_receipt_id', 'goods_receipt_line_id', 'disposition',
        'quantity', 'note', 'created_by_user_id', 'occurred_at', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'disposition' => GoodsReceiptQualityDisposition::class,
            'quantity' => 'decimal:6',
            'occurred_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<GoodsReceipt, $this> */
    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    /** @return BelongsTo<GoodsReceiptLine, $this> */
    public function line(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptLine::class, 'goods_receipt_line_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
