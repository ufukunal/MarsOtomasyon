<?php

namespace App\Support\Company;

use App\Models\Company;
use App\Models\CompanyDatabaseRegistration;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * New DOC2 company isolation boundary. Not wired into the legacy request stack
 * until the full migration and data backfill are completed and reviewed.
 */
final class CompanyDatabaseContext
{
    private static ?int $activeCompanyId = null;

    public static function activateForUser(int $companyId, int $userId): void
    {
        if ($userId < 1 || $companyId < 1) {
            throw new AuthorizationException('Şirket erişimi reddedildi.');
        }

        $company = Company::query()->whereKey($companyId)->where('is_active', true)->first();

        if (! $company || ! DB::connection('master')->table('company_user')
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->exists()) {
            throw new AuthorizationException('Bu şirkete erişim izniniz yok.');
        }

        self::bind($company);
    }

    /**
     * Trusted CLI/queue/installer use only. Never call with request-supplied IDs.
     */
    public static function activateForSystem(int $companyId): void
    {
        $company = Company::query()->whereKey($companyId)->where('is_active', true)->firstOrFail();

        self::bind($company);
    }

    public static function companyId(): ?int
    {
        return self::$activeCompanyId;
    }

    public static function ensure(): void
    {
        $id = self::$activeCompanyId;

        if ($id === null || config('database.connections.company.database') !== CompanyDatabaseName::forCompanyId($id)
            || CompanyContext::id() !== $id) {
            throw new LogicException('DOC2 şirket veritabanı bağlamı etkin değil.');
        }
    }

    public static function release(): void
    {
        self::$activeCompanyId = null;
        config(['database.connections.company.database' => null]);
        DB::purge('company');
        CompanyContext::clear();
    }

    public static function withinSystem(int $companyId, Closure $callback): mixed
    {
        $previousCompanyId = self::$activeCompanyId;

        try {
            self::activateForSystem($companyId);

            return $callback();
        } finally {
            self::release();

            if ($previousCompanyId !== null) {
                self::activateForSystem($previousCompanyId);
            }
        }
    }

    private static function bind(Company $company): void
    {
        $companyId = (int) $company->getKey();

        // Fail closed until migration, count reconciliation and restore rehearsal
        // explicitly activate the matching physical database in Master metadata.
        $registration = CompanyDatabaseRegistration::query()->whereKey($companyId)
            ->where('state', 'active')
            ->first();

        if (! $registration
            || $registration->database_name !== CompanyDatabaseName::forCompanyId($companyId)
            || $registration->verified_at === null
            || $registration->activated_at === null) {
            throw new LogicException('DOC2 şirket veritabanı doğrulanıp etkinleştirilmemiş.');
        }

        // Purge the previous connection before selecting another company's DB.
        self::release();
        config(['database.connections.company.database' => CompanyDatabaseName::forCompanyId($companyId)]);
        self::$activeCompanyId = $companyId;
        CompanyContext::use($companyId);
    }
}
