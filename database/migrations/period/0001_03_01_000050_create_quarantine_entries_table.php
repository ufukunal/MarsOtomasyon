<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('period')->create('quarantine_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->string('source_document_type', 40)->nullable();
            $table->unsignedBigInteger('source_document_id')->nullable();
            $table->unsignedBigInteger('source_line_id')->nullable();
            $table->decimal('quantity', 18, 3);
            $table->decimal('released_quantity', 18, 3)->default(0);
            $table->decimal('scrapped_quantity', 18, 3)->default(0);
            $table->decimal('unit_cost', 18, 4);
            $table->string('status', 20)->default('pending');
            $table->text('decision_note')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('created_by_name')->nullable();
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->string('decided_by_name')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'location_id', 'status']);
            $table->index(['source_document_type', 'source_document_id']);
        });

        DB::connection('period')->statement(
            'ALTER TABLE quarantine_entries
             ADD CONSTRAINT quarantine_entries_quantity_positive
             CHECK (quantity > 0)'
        );
        DB::connection('period')->statement(
            'ALTER TABLE quarantine_entries
             ADD CONSTRAINT quarantine_entries_decisions_nonnegative
             CHECK (released_quantity >= 0 AND scrapped_quantity >= 0)'
        );
        DB::connection('period')->statement(
            'ALTER TABLE quarantine_entries
             ADD CONSTRAINT quarantine_entries_decisions_within_quantity
             CHECK (released_quantity + scrapped_quantity <= quantity)'
        );
        DB::connection('period')->statement(
            'ALTER TABLE quarantine_entries
             ADD CONSTRAINT quarantine_entries_unit_cost_nonnegative
             CHECK (unit_cost >= 0)'
        );
        DB::connection('period')->statement(
            "ALTER TABLE quarantine_entries
             ADD CONSTRAINT quarantine_entries_status_valid
             CHECK (status IN ('pending','partial','released','scrapped'))"
        );
    }

    public function down(): void
    {
        Schema::connection('period')->dropIfExists('quarantine_entries');
    }
};
