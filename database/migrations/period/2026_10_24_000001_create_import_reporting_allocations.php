<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('period')->create('import_reporting_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('import_file_id')->constrained('import_files')->restrictOnDelete();
            $table->foreignId('container_id')->constrained('containers')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('source_type', 12);
            $table->unsignedBigInteger('source_id');
            $table->date('event_date');
            $table->decimal('quantity', 18, 3);
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('voided_by')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->timestamps();

            $table->foreign(['container_id', 'import_file_id'], 'ira_container_file_fk')
                ->references(['id', 'import_file_id'])->on('containers')->restrictOnDelete();
            $table->index(['import_file_id', 'product_id', 'event_date'], 'ira_group_product_date');
            $table->index(['source_type', 'source_id'], 'ira_source');
        });

        DB::connection('period')->statement(
            "ALTER TABLE import_reporting_allocations ADD CONSTRAINT ira_type_valid
             CHECK (source_type IN ('sales','return','fire','scrap'))"
        );
        DB::connection('period')->statement(
            'ALTER TABLE import_reporting_allocations ADD CONSTRAINT ira_qty_positive CHECK (quantity > 0)'
        );
        DB::connection('period')->statement(
            'CREATE UNIQUE INDEX ira_one_active_source_container
             ON import_reporting_allocations(source_type, source_id, container_id) WHERE voided_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::connection('period')->dropIfExists('import_reporting_allocations');
    }
};
