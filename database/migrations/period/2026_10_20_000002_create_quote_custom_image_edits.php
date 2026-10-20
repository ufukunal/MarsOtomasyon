<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('period')->create('quote_custom_image_edits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attachment_id')->constrained('attachments')->cascadeOnDelete();
            $table->string('disk', 30);
            $table->string('path');
            $table->string('mime', 100);
            $table->unsignedInteger('size');
            $table->jsonb('instructions')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['attachment_id', 'id'], 'quote_image_edits_lookup');
        });
    }

    public function down(): void
    {
        // Edited media must never be made unreachable by an accidental rollback.
        if (Schema::connection('period')->hasTable('quote_custom_image_edits')
            && \Illuminate\Support\Facades\DB::connection('period')->table('quote_custom_image_edits')->exists()) {
            throw new \RuntimeException('Görsel düzenleme geçmişi varken migration geri alınamaz.');
        }

        Schema::connection('period')->dropIfExists('quote_custom_image_edits');
    }
};
