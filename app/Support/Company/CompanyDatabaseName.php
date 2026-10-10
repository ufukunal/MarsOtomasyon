<?php

namespace App\Support\Company;

use InvalidArgumentException;

/**
 * DOC2 physical tenant database naming: stable across fiscal/calendar years.
 * Company IDs are immutable Master PKs; never derive SQL identifiers from user input.
 */
final class CompanyDatabaseName
{
    public static function forCompanyId(int $companyId): string
    {
        if ($companyId < 1) {
            throw new InvalidArgumentException('DOC2 şirket veritabanı için geçerli şirket kimliği gereklidir.');
        }

        return 'mars_company_'.$companyId;
    }
}
