<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('period')->table('documents', function (Blueprint $table): void {
            $table->decimal('additional_discount_amount', 18, 4)->default(0);
        });
        DB::connection('period')->statement(
            'ALTER TABLE documents ADD CONSTRAINT documents_additional_discount_nonnegative CHECK (additional_discount_amount >= 0)'
        );
    }

    public function down(): void
    {
        DB::connection('period')->statement(
            'ALTER TABLE documents DROP CONSTRAINT IF EXISTS documents_additional_discount_nonnegative'
        );
        Schema::connection('period')->table('documents', function (Blueprint $table): void {
            $table->dropColumn('additional_discount_amount');
        });
    }
};
