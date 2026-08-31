<?php

namespace App\Modules\B2B\Portal;

use App\Modules\SalesOrders\Models\SalesOrder;

final readonly class B2BOrderResult
{
    public function __construct(public SalesOrder $order, public ?string $warning, public bool $replayed) {}
}
