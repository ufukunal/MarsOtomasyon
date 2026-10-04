<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('master')->create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->foreignId('last_company_id')->nullable()->constrained('companies');
            $table->foreignId('last_period_id')->nullable()->constrained('periods');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::connection('master')->create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::connection('master')->create('company_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'user_id']);
        });

        Schema::connection('master')->create('period_user_access', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('period_id')->constrained('periods')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->jsonb('permission_overrides')->nullable();
            $table->timestamps();

            $table->unique(['period_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('master')->dropIfExists('period_user_access');
        Schema::connection('master')->dropIfExists('company_user');
        Schema::connection('master')->dropIfExists('password_reset_tokens');
        Schema::connection('master')->dropIfExists('users');
    }
};
