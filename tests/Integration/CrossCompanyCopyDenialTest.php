<?php

use App\Actions\Companies\CopyRecordsBetweenCompanies;
use App\Enums\CompanyCopyPermissionType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\AuthorizedPeriod;
use Tests\Support\IsolatedPostgres;

it('denies company-to-company copying without an explicit directional permission grant', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        AuthorizedPeriod::login();

        try {
            $db = DB::connection('master');
            $sourceId = $db->table('companies')->insertGetId([
                'code' => 'SRC-'.Str::random(10),
                'name' => 'V4 isolated source',
                'db_prefix' => 'src_v4',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach (CompanyCopyPermissionType::cases() as $type) {
                expect(fn () => app(CopyRecordsBetweenCompanies::class)
                    ->handle($sourceId, $type, [1, 2, 3]))
                    ->toThrow(HttpException::class);
            }
        } finally {
            AuthorizedPeriod::logout();
        }
    });
});
