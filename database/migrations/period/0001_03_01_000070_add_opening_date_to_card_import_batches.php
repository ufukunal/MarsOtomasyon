<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('period')->table('card_import_batches', function (Blueprint $table): void {
            $table->date('opening_date')->nullable()->after('error_mode');
        });
    }

    public function down(): void
    {
        Schema::connection('period')->table('card_import_batches', function (Blueprint $table): void {
            $table->dropColumn('opening_date');
        });
    }
};
