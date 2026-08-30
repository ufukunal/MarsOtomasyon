<?php

namespace App\Modules\Imports\Models;

use Illuminate\Database\Eloquent\Model;

final class ImportExpense extends Model
{
    protected $fillable = ['company_id', 'import_file_id', 'expense_code', 'description', 'amount', 'currency_code', 'status', 'allocation_basis', 'finalized_at', 'note'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:6', 'finalized_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }
}
