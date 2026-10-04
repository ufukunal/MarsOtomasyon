<?php

namespace App\Actions\Sales;

use App\Models\Company;
use App\Models\Period\Contact;
use App\Support\Period\PeriodContext;
use Carbon\CarbonImmutable;

final class ResolveSalesDueDate
{
    public function handle(Contact $contact, string $documentDate): string
    {
        $days = $contact->term_days;

        if ($days === null) {
            $days = (int) Company::query()
                ->whereKey(PeriodContext::companyId())
                ->value('default_term_days');
        }

        return CarbonImmutable::parse($documentDate)
            ->addDays($days ?? 30)
            ->toDateString();
    }
}
