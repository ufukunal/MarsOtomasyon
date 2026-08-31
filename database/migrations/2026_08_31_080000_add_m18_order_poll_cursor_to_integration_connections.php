<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integration_connections', function (Blueprint $table): void {
            $table->timestampTz('order_poll_watermark_at')->nullable();
            $table->jsonb('order_poll_cursor')->nullable();
            $table->index(
                ['company_id', 'provider', 'order_poll_watermark_at'],
                'integration_connections_order_poll_watermark_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('integration_connections', function (Blueprint $table): void {
            $table->dropIndex('integration_connections_order_poll_watermark_idx');
            $table->dropColumn(['order_poll_watermark_at', 'order_poll_cursor']);
        });
    }
};
