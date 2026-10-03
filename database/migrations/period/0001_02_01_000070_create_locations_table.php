<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('period')->create('locations', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('kind', 20);
            $table->string('plate', 20)->nullable();
            $table->foreignId('subcontractor_contact_id')->nullable()->constrained('contacts')->restrictOnDelete();
            $table->text('address')->nullable();
            $table->string('search_index')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });

        DB::connection('period')->statement(
            "ALTER TABLE locations ADD CONSTRAINT locations_kind_phase1_valid
             CHECK (kind IN ('warehouse','branch','vehicle'))"
        );
        DB::connection('period')->statement(
            "ALTER TABLE locations ADD CONSTRAINT locations_plate_vehicle
             CHECK (kind = 'vehicle' OR plate IS NULL)"
        );
        DB::connection('period')->statement(
            'CREATE UNIQUE INDEX locations_one_default
             ON locations ((1)) WHERE is_default = true'
        );
        DB::connection('period')->statement(
            'CREATE INDEX locations_search_index_trgm ON locations USING gin (search_index gin_trgm_ops)'
        );
    }

    public function down(): void
    {
        Schema::connection('period')->dropIfExists('locations');
    }
};
