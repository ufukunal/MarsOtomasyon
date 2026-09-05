<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outbox_messages', function (Blueprint $table): void {
            $table->index(
                ['status', 'lease_expires_at', 'retry_capability'],
                'outbox_expired_lease_retry_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('outbox_messages', function (Blueprint $table): void {
            $table->dropIndex('outbox_expired_lease_retry_idx');
        });
    }
};
