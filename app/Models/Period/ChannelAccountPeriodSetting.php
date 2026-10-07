<?php

namespace App\Models\Period;

use App\Models\PeriodModel;
use App\Support\Concurrency\HasOptimisticLock;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChannelAccountPeriodSetting extends PeriodModel
{
    use HasOptimisticLock;

    protected $fillable = [
        'channel_account_id',
        'marketplace_customer_contact_id',
    ];

    protected function casts(): array
    {
        return [
            'channel_account_id' => 'integer',
            'marketplace_customer_contact_id' => 'integer',
            'version' => 'integer',
        ];
    }

    /** @return BelongsTo<Contact, $this> */
    public function marketplaceCustomerContact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'marketplace_customer_contact_id');
    }
}
