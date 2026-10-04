<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('period')->create('units', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->boolean('is_base')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });

        Schema::connection('period')->create('unit_conversions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('from_unit_id')->constrained('units')->restrictOnDelete();
            $table->foreignId('to_unit_id')->constrained('units')->restrictOnDelete();
            $table->decimal('factor', 18, 6);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['from_unit_id', 'to_unit_id']);
        });

        DB::connection('period')->statement(
            'ALTER TABLE unit_conversions ADD CONSTRAINT unit_conversions_positive_factor CHECK (factor > 0)'
        );
        DB::connection('period')->statement(
            'ALTER TABLE unit_conversions ADD CONSTRAINT unit_conversions_distinct_units CHECK (from_unit_id <> to_unit_id)'
        );
    }

    public function down(): void
    {
        Schema::connection('period')->dropIfExists('unit_conversions');
        Schema::connection('period')->dropIfExists('units');
    }
};
