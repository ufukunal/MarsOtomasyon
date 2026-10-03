<?php

namespace App\Support\Company;

use Spatie\Permission\PermissionRegistrar;

final class CompanyContext
{
    private static ?int $companyId = null;

    public static function use(int $companyId): void
    {
        self::$companyId = $companyId;
        app(PermissionRegistrar::class)->setPermissionsTeamId($companyId);
    }

    public static function id(): ?int
    {
        return self::$companyId;
    }

    public static function clear(): void
    {
        self::$companyId = null;
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
    }
}
