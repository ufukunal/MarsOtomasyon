<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('master')->create('companies', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('db_prefix', 30)->unique();
            $table->string('tax_office')->nullable();
            $table->string('tax_number', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 60)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('logo_path')->nullable();
            $table->unsignedSmallInteger('default_term_days')->default(30);
            $table->decimal('cost_deviation_threshold', 7, 4)->default(25);
            $table->char('base_currency', 3)->default('TRY');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('master')->dropIfExists('companies');
    }
};
