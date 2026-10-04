<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('master')->table('periods', function (Blueprint $table): void {
            $table->string('schema_version', 255)->nullable()->after('version');
        });
    }

    public function down(): void
    {
        Schema::connection('master')->table('periods', function (Blueprint $table): void {
            $table->dropColumn('schema_version');
        });
    }
};
