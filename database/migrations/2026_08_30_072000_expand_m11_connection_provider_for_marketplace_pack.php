<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE integration_connections DROP CONSTRAINT IF EXISTS m11_connection_provider');
        DB::statement("ALTER TABLE integration_connections ADD CONSTRAINT m11_connection_provider CHECK (provider IN ('woocommerce','trendyol','hepsiburada','amazon','n11','pttavm','idefix','allesgo'))");
    }

    public function down(): void
    {
        if (DB::table('integration_connections')->whereIn('provider', ['n11', 'pttavm', 'idefix', 'allesgo'])->exists()) {
            return;
        }

        DB::statement('ALTER TABLE integration_connections DROP CONSTRAINT IF EXISTS m11_connection_provider');
        DB::statement("ALTER TABLE integration_connections ADD CONSTRAINT m11_connection_provider CHECK (provider IN ('woocommerce','trendyol','hepsiburada','amazon'))");
    }
};
