<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('master')->table('companies', function (Blueprint $table): void {
            $table->decimal('default_vat_rate', 7, 4)->default(20);
        });
    }

    public function down(): void
    {
        Schema::connection('master')->table('companies', function (Blueprint $table): void {
            $table->dropColumn('default_vat_rate');
        });
    }
};
