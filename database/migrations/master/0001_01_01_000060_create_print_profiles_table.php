<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('master')->create('print_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('machine_key', 64)->nullable();
            $table->string('print_type', 30);
            $table->string('printer_name')->nullable();
            $table->string('paper_code', 20)->nullable();
            $table->decimal('width_mm', 8, 2)->nullable();
            $table->decimal('height_mm', 8, 2)->nullable();
            $table->jsonb('settings')->nullable();
            $table->unsignedBigInteger('template_id')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });

        DB::connection('master')->statement(<<<'SQL'
            CREATE UNIQUE INDEX print_profiles_unique
            ON print_profiles (company_id, user_id, machine_key, print_type)
            NULLS NOT DISTINCT
        SQL);
    }

    public function down(): void
    {
        Schema::connection('master')->dropIfExists('print_profiles');
    }
};
