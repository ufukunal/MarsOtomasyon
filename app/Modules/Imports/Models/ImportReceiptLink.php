<?php

namespace App\Modules\Imports\Models;

use Illuminate\Database\Eloquent\Model;

final class ImportReceiptLink extends Model
{
    protected $fillable = ['company_id', 'import_file_id', 'import_item_id', 'goods_receipt_id', 'goods_receipt_line_id', 'linked_quantity'];

    protected function casts(): array
    {
        return ['linked_quantity' => 'decimal:6', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }
}
