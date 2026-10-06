<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $sales_order_id
 * @property int $channel_account_id
 * @property string $external_order_id
 * @property array<string, mixed>|null $campaign_metadata
 */
class ChannelOrderSnapshot extends PeriodModel
{
    protected $fillable = [
        'sales_order_id',
        'channel_account_id',
        'external_order_id',
        'external_order_no',
        'buyer_name',
        'recipient_name',
        'phone',
        'email',
        'address',
        'city',
        'district',
        'postcode',
        'cargo_company',
        'cargo_code',
        'external_shipment_id',
        'external_package_id',
        'campaign_metadata',
    ];

    protected function casts(): array
    {
        return [
            'sales_order_id' => 'integer',
            'channel_account_id' => 'integer',
            'campaign_metadata' => 'array',
        ];
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'sales_order_id');
    }
}
