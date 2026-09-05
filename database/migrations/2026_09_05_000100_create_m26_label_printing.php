<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('label_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 64);
            $table->string('name', 160);
            $table->string('target_type', 32);
            $table->string('format', 16);
            $table->decimal('width_mm', 8, 2)->nullable();
            $table->decimal('height_mm', 8, 2)->nullable();
            $table->unsignedInteger('dpi')->default(203);
            $table->text('body');
            $table->json('config')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'target_type', 'format']);
        });

        Schema::create('printer_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 64);
            $table->string('name', 160);
            $table->string('driver', 16);
            $table->decimal('width_mm', 8, 2)->nullable();
            $table->decimal('height_mm', 8, 2)->nullable();
            $table->unsignedInteger('dpi')->default(203);
            $table->json('config')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'driver']);
        });

        Schema::create('label_prints', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('label_template_id')->constrained('label_templates')->restrictOnDelete();
            $table->foreignId('printer_profile_id')->nullable()->constrained('printer_profiles')->nullOnDelete();
            $table->string('target_type', 32);
            $table->unsignedBigInteger('target_id');
            $table->foreignId('barcode_id')->nullable()->constrained('barcodes')->nullOnDelete();
            $table->string('format', 16);
            $table->json('payload_snapshot');
            $table->json('template_snapshot');
            $table->json('printer_snapshot')->nullable();
            $table->longText('output_base64');
            $table->char('content_hash', 64);
            $table->foreignId('reprint_of_id')->nullable()->constrained('label_prints')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'target_type', 'target_id']);
            $table->index(['company_id', 'content_hash']);
            $table->index(['company_id', 'reprint_of_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('label_prints');
        Schema::dropIfExists('printer_profiles');
        Schema::dropIfExists('label_templates');
    }
};
