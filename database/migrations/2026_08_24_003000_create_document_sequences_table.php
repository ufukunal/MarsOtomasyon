<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('document_type', 64);
            $table->string('series_code', 32)->default('default');
            $table->string('prefix', 32)->default('');
            $table->smallInteger('padding')->default(6);
            $table->bigInteger('next_value')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['company_id', 'document_type', 'series_code']);
            $table->index(['company_id', 'is_active']);
        });

        DB::statement("ALTER TABLE document_sequences ADD CONSTRAINT document_sequences_type_check CHECK (document_type ~ '^[a-z0-9._-]+$')");
        DB::statement("ALTER TABLE document_sequences ADD CONSTRAINT document_sequences_series_check CHECK (series_code ~ '^[a-z0-9._-]+$')");
        DB::statement('ALTER TABLE document_sequences ADD CONSTRAINT document_sequences_padding_check CHECK (padding BETWEEN 1 AND 18)');
        DB::statement('ALTER TABLE document_sequences ADD CONSTRAINT document_sequences_next_value_check CHECK (next_value >= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};
