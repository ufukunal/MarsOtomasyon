<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('period')->create('card_import_batches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type', 30);
            $table->string('source_disk', 30)->default('imports');
            $table->string('source_path');
            $table->string('original_name');
            $table->char('file_hash', 64);
            $table->jsonb('mapping');
            $table->string('error_mode', 20)->default('cancel_all');
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('success_rows')->default(0);
            $table->unsignedInteger('error_rows')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('created_by_name')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamps();

            $table->unique(['file_hash', 'type']);
        });

        Schema::connection('period')->create('card_import_errors', function (Blueprint $table): void {
            $table->id();
            $table->uuid('batch_id');
            $table->unsignedInteger('row_no');
            $table->string('column_name')->nullable();
            $table->text('value')->nullable();
            $table->text('message');
            $table->timestamps();

            $table->foreign('batch_id')->references('id')->on('card_import_batches')->cascadeOnDelete();
            $table->index(['batch_id', 'row_no']);
        });

        DB::connection('period')->statement(
            "ALTER TABLE card_import_batches ADD CONSTRAINT card_import_batches_type_valid
             CHECK (type IN ('contact','product','price_list','opening_stock'))"
        );
        DB::connection('period')->statement(
            "ALTER TABLE card_import_batches ADD CONSTRAINT card_import_batches_error_mode_valid
             CHECK (error_mode IN ('cancel_all','skip_invalid'))"
        );
        DB::connection('period')->statement(
            "ALTER TABLE card_import_batches ADD CONSTRAINT card_import_batches_status_valid
             CHECK (status IN ('pending','processing','done','failed'))"
        );
    }

    public function down(): void
    {
        Schema::connection('period')->dropIfExists('card_import_errors');
        Schema::connection('period')->dropIfExists('card_import_batches');
    }
};
