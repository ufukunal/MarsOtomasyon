<?php

namespace App\Modules\SalesInvoices\Models;

use Illuminate\Database\Eloquent\Model;

final class SalesInvoiceOrderLineCapacity extends Model
{
    protected $table = 'sales_invoice_order_line_capacity';

    protected $primaryKey = 'sales_order_line_id';

    public $incrementing = false;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'sales_order_id' => 'integer',
            'sales_order_line_id' => 'integer',
            'ordered_quantity' => 'decimal:6',
            'cancelled_quantity' => 'decimal:6',
            'net_invoiced_quantity' => 'decimal:6',
            'draft_quantity' => 'decimal:6',
            'previous_quantity' => 'decimal:6',
            'remaining_quantity' => 'decimal:6',
        ];
    }
}
