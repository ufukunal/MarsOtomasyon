<?php

namespace App\Models;

/**
 * DOC2 Master metadata. Rows are never automatically provisioned by requests.
 */
final class CompanyDatabaseRegistration extends MasterModel
{
    protected $table = 'company_database_registry';

    protected $primaryKey = 'company_id';

    public $incrementing = false;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'verified_at' => 'datetime',
            'activated_at' => 'datetime',
        ];
    }
}
