<?php

namespace App\Models;

use App\Support\Company\CompanyDatabaseContext;
use Illuminate\Database\Eloquent\Model;

/**
 * DOC2 business model base: never query a company DB without an active tenant.
 * Existing PeriodModel subclasses remain legacy until explicitly migrated.
 */
abstract class CompanyModel extends Model
{
    protected $connection = 'company';

    public function getConnectionName()
    {
        CompanyDatabaseContext::ensure();

        return parent::getConnectionName();
    }
}
