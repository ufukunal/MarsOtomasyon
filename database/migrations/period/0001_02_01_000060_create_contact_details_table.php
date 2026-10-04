<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('period')->create('contact_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('color', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });

        Schema::connection('period')->create('contact_category', function (Blueprint $table): void {
            $table->foreignId('contact_id')->constrained('contacts')->restrictOnDelete();
            $table->foreignId('contact_category_id')->constrained('contact_categories')->restrictOnDelete();

            $table->primary(['contact_id', 'contact_category_id']);
        });

        Schema::connection('period')->create('contact_addresses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contact_id')->constrained('contacts')->restrictOnDelete();
            $table->string('type', 20);
            $table->string('title')->nullable();
            $table->text('address');
            $table->string('city', 60)->nullable();
            $table->string('district', 60)->nullable();
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });

        DB::connection('period')->statement(
            'CREATE UNIQUE INDEX contact_addresses_one_default_per_type
             ON contact_addresses (contact_id, type) WHERE is_default = true'
        );

        Schema::connection('period')->create('contact_people', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contact_id')->constrained('contacts')->restrictOnDelete();
            $table->string('name');
            $table->string('title')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });

        DB::connection('period')->statement(
            'CREATE UNIQUE INDEX contact_people_one_default
             ON contact_people (contact_id) WHERE is_default = true'
        );

        Schema::connection('period')->create('contact_banks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contact_id')->constrained('contacts')->restrictOnDelete();
            $table->string('bank_name')->nullable();
            $table->string('iban', 26);
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['contact_id', 'iban']);
        });

        DB::connection('period')->statement(
            'CREATE UNIQUE INDEX contact_banks_one_default
             ON contact_banks (contact_id) WHERE is_default = true'
        );
    }

    public function down(): void
    {
        Schema::connection('period')->dropIfExists('contact_banks');
        Schema::connection('period')->dropIfExists('contact_people');
        Schema::connection('period')->dropIfExists('contact_addresses');
        Schema::connection('period')->dropIfExists('contact_category');
        Schema::connection('period')->dropIfExists('contact_categories');
    }
};
