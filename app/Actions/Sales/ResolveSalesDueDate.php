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
            $companyDays = Company::query()
                ->whereKey(PeriodContext::companyId())
                ->value('default_term_days');
            $days = $companyDays === null ? 30 : (int) $companyDays;
        }

        return CarbonImmutable::parse($documentDate)
            ->addDays($days)
            ->toDateString();
    }
}
