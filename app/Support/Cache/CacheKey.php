<?php

namespace App\Support\Cache;

use App\Support\Period\PeriodContext;
use RuntimeException;

final class CacheKey
{
    public static function period(string $key): string
    {
        $companyId = PeriodContext::companyId();
        $year = PeriodContext::year();

        if (! $companyId || ! $year) {
            throw new RuntimeException('Dönem bağlamı kurulmadan önbellek anahtarı üretilemez.');
        }

        return "c{$companyId}:y{$year}:{$key}";
    }

    public static function master(string $key): string
    {
        $companyId = PeriodContext::companyId();

        if (! $companyId) {
            throw new RuntimeException('Şirket bağlamı kurulmadan önbellek anahtarı üretilemez.');
        }

        return "c{$companyId}:{$key}";
    }

    public static function global(string $key): string
    {
        return "g:{$key}";
    }
}
