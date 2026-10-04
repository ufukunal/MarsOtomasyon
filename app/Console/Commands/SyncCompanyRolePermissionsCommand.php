<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Support\Auth\CompanyRoleProvisioner;
use Illuminate\Console\Command;

class SyncCompanyRolePermissionsCommand extends Command
{
    protected $signature = 'permissions:sync-company-roles';

    protected $description = 'Tüm şirketlerin sistem rollerini güncel izin matrisiyle senkronize eder';

    public function handle(CompanyRoleProvisioner $provisioner): int
    {
        try {
            Company::query()
                ->orderBy('id')
                ->each(function (Company $company) use ($provisioner): void {
                    $provisioner->handle($company);
                    $this->line("{$company->id} · {$company->name}: roller senkronize edildi.");
                });
        } finally {
            setPermissionsTeamId(null);
        }

        $this->info('Şirket rol izinleri güncel matrisle senkronize edildi.');

        return self::SUCCESS;
    }
}
