<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('period')->table('documents', function (Blueprint $table): void {
            $table->jsonb('ui_meta')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('period')->table('documents', function (Blueprint $table): void {
            $table->dropColumn('ui_meta');
        });
    }
};
