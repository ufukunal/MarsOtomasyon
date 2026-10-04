<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('period')->table('quarantine_entries', function (Blueprint $table): void {
            $table->decimal('reversed_quantity', 18, 3)->default(0)->after('scrapped_quantity');
            $table->timestamp('reversed_at')->nullable()->after('decided_at');
        });

        DB::connection('period')->statement(
            'ALTER TABLE quarantine_entries ADD CONSTRAINT quarantine_reversed_non_negative CHECK (reversed_quantity >= 0)'
        );
        DB::connection('period')->statement(
            'ALTER TABLE quarantine_entries ADD CONSTRAINT quarantine_decisions_within_quantity CHECK (released_quantity + scrapped_quantity + reversed_quantity <= quantity)'
        );
    }

    public function down(): void
    {
        DB::connection('period')->statement(
            'ALTER TABLE quarantine_entries DROP CONSTRAINT IF EXISTS quarantine_decisions_within_quantity'
        );
        DB::connection('period')->statement(
            'ALTER TABLE quarantine_entries DROP CONSTRAINT IF EXISTS quarantine_reversed_non_negative'
        );

        Schema::connection('period')->table('quarantine_entries', function (Blueprint $table): void {
            $table->dropColumn(['reversed_quantity', 'reversed_at']);
        });
    }
};
