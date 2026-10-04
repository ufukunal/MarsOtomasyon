<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('master')->create('periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->unsignedSmallInteger('year');
            $table->string('database_name', 64)->unique();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 12)->default('active');
            $table->foreignId('carried_from_period_id')->nullable()->constrained('periods');
            $table->timestamp('carried_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['company_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::connection('master')->dropIfExists('periods');
    }
};
