<?php

namespace App\Modules\Core\Posting;

use App\Modules\Core\Enums\PostingPeriodStatus;
use App\Modules\Core\Models\PostingPeriod;
use DateTimeInterface;
use DomainException;

final class PostingPeriodGuard
{
    public function assertOpen(int $companyId, DateTimeInterface|string $date): PostingPeriod
    {
        $value = $date instanceof DateTimeInterface ? $date->format('Y-m-d') : $date;

        $period = PostingPeriod::query()
            ->where('company_id', $companyId)
            ->where('starts_on', '<=', $value)
            ->where('ends_on', '>=', $value)
            ->first();

        if (! $period instanceof PostingPeriod) {
            throw new DomainException('İşlem tarihi için muhasebe dönemi bulunamadı.');
        }

        if ($period->status !== PostingPeriodStatus::Open) {
            throw new DomainException('İşlem tarihi kapalı bir muhasebe döneminde.');
        }

        return $period;
    }
}
