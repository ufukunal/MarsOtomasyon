<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('period')->create('transfers', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 40)->nullable()->unique();
            $table->foreignId('from_location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('to_location_id')->constrained('locations')->restrictOnDelete();
            $table->date('transfer_date');
            $table->string('status', 24)->default('draft');
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('posted_by')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->index(['transfer_date', 'status']);
            $table->index(['from_location_id', 'to_location_id']);
        });

        DB::connection('period')->statement(
            "ALTER TABLE transfers
             ADD CONSTRAINT transfers_status_valid
             CHECK (status IN ('draft','in_transit','partially_received','received','cancelled'))"
        );
        DB::connection('period')->statement(
            'ALTER TABLE transfers
             ADD CONSTRAINT transfers_locations_different
             CHECK (from_location_id <> to_location_id)'
        );

        Schema::connection('period')->create('transfer_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transfer_id')->constrained('transfers')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('quantity', 18, 3);
            $table->decimal('received_quantity', 18, 3)->default(0);
            $table->timestamps();

            $table->index(['transfer_id', 'product_id']);
        });

        DB::connection('period')->statement(
            'ALTER TABLE transfer_lines
             ADD CONSTRAINT transfer_lines_quantity_positive
             CHECK (quantity > 0)'
        );
        DB::connection('period')->statement(
            'ALTER TABLE transfer_lines
             ADD CONSTRAINT transfer_lines_received_valid
             CHECK (received_quantity >= 0 AND received_quantity <= quantity)'
        );
    }

    public function down(): void
    {
        Schema::connection('period')->dropIfExists('transfer_lines');
        Schema::connection('period')->dropIfExists('transfers');
    }
};
