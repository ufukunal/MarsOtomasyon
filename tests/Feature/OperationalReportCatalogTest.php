<?php

namespace Tests\Feature;

use App\Modules\Reports\OperationalReportCatalog;
use Tests\TestCase;

final class OperationalReportCatalogTest extends TestCase
{
    private const EXPECTED_KEYS = [
        'ACC-01', 'ACC-02', 'ACC-03', 'ACC-04', 'ACC-05',
        'SAL-01', 'SAL-02', 'SAL-03', 'SAL-04', 'SAL-05',
        'PUR-01', 'PUR-02', 'PUR-03', 'PUR-04', 'PUR-05',
        'STK-01', 'STK-02', 'STK-03', 'STK-04', 'STK-05',
        'PRD-01', 'PRD-02', 'PRD-03', 'PRD-04', 'PRD-05',
        'IMP-01', 'IMP-02', 'IMP-03', 'IMP-04', 'IMP-05',
        'AUT-01', 'AUT-02', 'AUT-03', 'AUT-04', 'AUT-05',
        'MGT-01', 'MGT-02', 'MGT-03', 'MGT-04', 'MGT-05',
    ];

    public function test_official_report_catalog_contains_exactly_forty_stable_keys(): void
    {
        $catalog = $this->app->make(OperationalReportCatalog::class);
        self::assertSame(self::EXPECTED_KEYS, $catalog->keys());
        self::assertCount(40, $catalog->definitions());
    }

    public function test_every_report_key_executes_a_real_tenant_scoped_query_on_postgresql_schema(): void
    {
        $catalog = $this->app->make(OperationalReportCatalog::class);
        $tenantId = 2147483000;

        foreach (self::EXPECTED_KEYS as $key) {
            $query = $catalog->query($key, $tenantId);
            self::assertContains($tenantId, $query->getBindings(), $key.' must bind the active company id.');
            self::assertCount(0, $catalog->run($key, $tenantId, 1), $key.' should execute cleanly against the migrated schema.');
        }
    }
}
