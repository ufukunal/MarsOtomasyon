<?php

namespace App\Actions\Sales;

use App\DataObjects\Sales\SalesRiskProjection;
use App\Models\Period\Contact;
use App\Models\Period\Document;
use DomainException;

final class CalculateSalesRiskProjection
{
    public function handle(Document $order): SalesRiskProjection
    {
        // The risk limit and Contact::balance() are TRY values. Do not treat
        // foreign-currency order totals as TRY without a verified conversion.
        $currency = strtoupper((string) ($order->currency ?: 'TRY'));
        if ($currency !== 'TRY') {
            throw new DomainException('TRY dışındaki satış siparişi için doğrulanmış risk dönüşümü bulunmuyor. Risk hesabı yapılamaz.');
        }

        $contact = Contact::query()->findOrFail((int) $order->contact_id);
        $balance = $contact->balance();
        $knownExposure = bcadd($balance, (string) $order->grand_total, 4);
        $riskLimit = bcadd((string) $contact->risk_limit, '0', 4);
        $exceeded = bccomp($riskLimit, '0', 4) > 0 && bccomp($knownExposure, $riskLimit, 4) > 0;
        $over = $exceeded ? bcsub($knownExposure, $riskLimit, 4) : '0.0000';

        return new SalesRiskProjection(
            currentBalance: $balance,
            orderTotal: (string) $order->grand_total,
            securityRisk: null,
            projectionComplete: false,
            knownExposure: $knownExposure,
            riskLimit: $riskLimit,
            knownLimitExceeded: $exceeded,
            knownOverLimit: $over,
        );
    }
}
