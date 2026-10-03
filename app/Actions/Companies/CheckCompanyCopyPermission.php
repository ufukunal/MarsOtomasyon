<?php

namespace App\Actions\Companies;

use App\Enums\CompanyCopyPermissionType;
use App\Models\CompanyCopyPermission;

final class CheckCompanyCopyPermission
{
    public function handle(
        int $sourceCompanyId,
        int $targetCompanyId,
        CompanyCopyPermissionType $type,
    ): bool {
        return CompanyCopyPermission::allows(
            $sourceCompanyId,
            $targetCompanyId,
            $type,
        );
    }
}
