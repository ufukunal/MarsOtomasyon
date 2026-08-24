<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32);
            $table->string('name', 160);
            $table->string('status', 16)->default('active');
            $table->char('base_currency_code', 3)->default('TRY');
            $table->string('timezone', 64)->default('Europe/Istanbul');
            $table->timestampsTz();
        });

        DB::statement('CREATE UNIQUE INDEX companies_code_lower_unique ON companies (lower(code))');
        DB::statement("ALTER TABLE companies ADD CONSTRAINT companies_status_check CHECK (status IN ('active', 'suspended', 'archived'))");
        DB::statement("ALTER TABLE companies ADD CONSTRAINT companies_currency_check CHECK (base_currency_code ~ '^[A-Z]{3}$')");

        Schema::create('branches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('code', 32);
            $table->string('name', 160);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        DB::statement('CREATE UNIQUE INDEX branches_company_code_lower_unique ON branches (company_id, lower(code))');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 160);
            $table->string('email', 255);
            $table->timestampTz('email_verified_at')->nullable();
            $table->string('password');
            $table->string('status', 16)->default('active');
            $table->timestampTz('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestampsTz();
        });

        DB::statement('CREATE UNIQUE INDEX users_email_lower_unique ON users (lower(email))');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status IN ('active', 'inactive'))");

        Schema::create('company_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestampTz('joined_at')->nullable();
            $table->timestampsTz();

            $table->unique(['company_id', 'user_id']);
            $table->index(['user_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_memberships');
        Schema::dropIfExists('users');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('companies');
    }
};
