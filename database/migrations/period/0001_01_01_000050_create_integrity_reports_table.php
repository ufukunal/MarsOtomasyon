<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('period')->create('integrity_reports', function (Blueprint $table): void {
            $table->id();
            $table->string('check_name', 40);
            $table->timestamp('run_at');
            $table->unsignedInteger('checked_count')->default(0);
            $table->unsignedInteger('mismatch_count')->default(0);
            $table->json('details')->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->timestamps();

            $table->index(['check_name', 'run_at']);
        });

        DB::connection('period')->statement(
            'ALTER TABLE number_series ADD CONSTRAINT number_series_padding_valid CHECK (padding BETWEEN 1 AND 20)'
        );

        DB::connection('period')->statement(
            'ALTER TABLE posting_periods ADD CONSTRAINT posting_periods_month_valid CHECK (month BETWEEN 1 AND 12)'
        );

        DB::connection('period')->statement(
            "ALTER TABLE posting_periods ADD CONSTRAINT posting_periods_status_valid CHECK (status IN ('open','closed'))"
        );
    }

    public function down(): void
    {
        DB::connection('period')->statement(
            'ALTER TABLE number_series DROP CONSTRAINT IF EXISTS number_series_padding_valid'
        );
        DB::connection('period')->statement(
            'ALTER TABLE posting_periods DROP CONSTRAINT IF EXISTS posting_periods_month_valid'
        );
        DB::connection('period')->statement(
            'ALTER TABLE posting_periods DROP CONSTRAINT IF EXISTS posting_periods_status_valid'
        );

        Schema::connection('period')->dropIfExists('integrity_reports');
    }
};
