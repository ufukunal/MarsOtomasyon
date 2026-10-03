<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('period')->create('attachments', function (Blueprint $table): void {
            $table->id();
            $table->morphs('attachable');
            $table->string('disk', 30)->default('attachments');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 100);
            $table->unsignedBigInteger('size');
            $table->string('collection', 40)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->string('uploaded_by_name')->nullable();
            $table->timestamps();

            $table->index(['attachable_type', 'attachable_id'], 'attachments_owner_index');
        });
    }

    public function down(): void
    {
        Schema::connection('period')->dropIfExists('attachments');
    }
};
