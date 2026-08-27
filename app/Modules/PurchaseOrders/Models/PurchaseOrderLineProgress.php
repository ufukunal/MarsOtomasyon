<?php

namespace App\Modules\PurchaseOrders\Models;

use Illuminate\Database\Eloquent\Model;

final class PurchaseOrderLineProgress extends Model
{
    protected $table = 'purchase_order_line_progress';

    protected $primaryKey = 'purchase_order_line_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'ordered_quantity' => 'decimal:6',
            'net_received_quantity' => 'decimal:6',
            'net_invoiced_quantity' => 'decimal:6',
            'cancelled_quantity' => 'decimal:6',
            'receive_remaining_quantity' => 'decimal:6',
            'invoice_remaining_quantity' => 'decimal:6',
        ];
    }
}
