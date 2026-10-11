<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Separate shipping paperwork from G-207 physical stock adjustment documents.
        Schema::connection('period')->create('transport_slips', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 40)->unique();
            $table->date('slip_date');
            $table->foreignId('contact_id')->constrained('contacts')->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->restrictOnDelete();
            $table->string('order_reference', 80)->nullable();
            $table->string('dispatch_reference', 80)->nullable();
            $table->string('shipping_type', 24);
            $table->string('carrier', 180);
            $table->string('destination_warehouse', 180);
            $table->unsignedInteger('package_count');
            $table->decimal('weight_kg', 12, 2)->nullable();
            $table->string('tracking_number', 120)->nullable();
            $table->boolean('contains_glass')->default(false);
            $table->boolean('fragile')->default(false);
            $table->boolean('keep_upright')->default(false);
            $table->boolean('do_not_stack')->default(false);
            $table->string('delivery_company', 180);
            $table->string('delivery_person', 120)->nullable();
            $table->string('delivery_phone', 40);
            $table->string('delivery_city', 100);
            $table->string('delivery_district', 100);
            $table->text('delivery_address');
            $table->text('note')->nullable();
            $table->string('status', 18)->default('draft');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index(['slip_date', 'status']);
            $table->index(['contact_id', 'slip_date']);
            $table->index(['carrier', 'status']);
        });

        Schema::connection('period')->create('transport_slip_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transport_slip_id')->constrained('transport_slips')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('quantity', 18, 3);
            $table->timestamps();
            $table->index(['transport_slip_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('period')->dropIfExists('transport_slip_items');
        Schema::connection('period')->dropIfExists('transport_slips');
    }
};
