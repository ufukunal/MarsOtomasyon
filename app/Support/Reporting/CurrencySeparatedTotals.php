<?php

namespace App\Support\Reporting;

/**
 * Preserve the monetary unit of every report subtotal. Without an explicit
 * currency filter, a multi-currency report may never return a scalar sum.
 */
final class CurrencySeparatedTotals
{
    /**
     * @param  iterable<object>  $groups  SQL rows already grouped by currency
     * @param  list<string>  $keys
     * @return array<string,string>
     */
    public static function fromGroups(iterable $groups, array $keys, ?string $selectedCurrency = null): array
    {
        $values = array_fill_keys($keys, []);
        foreach ($groups as $group) {
            $currency = strtoupper((string) ($group->currency ?? ''));
            if (preg_match('/^[A-Z]{3}$/D', $currency) !== 1) {
                throw new \DomainException('Finansal rapor toplamında geçersiz para birimi bulundu.');
            }
            foreach ($keys as $key) {
                $values[$key][$currency] = (string) ($group->{$key} ?? '0');
            }
        }

        $totals = [];
        foreach ($values as $key => $currencies) {
            ksort($currencies);
            if ($selectedCurrency !== null && $selectedCurrency !== '') {
                // Keep numeric totals for explicitly filtered dashboards/exports.
                $code = strtoupper($selectedCurrency);
                $totals[$key] = $currencies[$code] ?? '0';
            } elseif ($currencies === []) {
                $totals[$key] = '0';
            } else {
                $parts = [];
                foreach ($currencies as $code => $amount) {
                    $parts[] = $amount.' '.$code;
                }
                $totals[$key] = implode(' / ', $parts);
            }
        }

        return $totals;
    }
}
