<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::connection('period')->statement(
            'CREATE EXTENSION IF NOT EXISTS pg_trgm'
        );
    }

    public function down(): void
    {
        // pg_trgm shared period capability'dir. Bir migration rollback'inde
        // başka tablo/index kullanımlarını kırmamak için extension düşürülmez.
    }
};
