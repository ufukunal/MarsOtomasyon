<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('period')->table('channel_sync_events', function (Blueprint $table): void {
            $table->jsonb('safe_metadata')->nullable()->after('payload_hash');
        });
    }

    public function down(): void
    {
        Schema::connection('period')->table('channel_sync_events', function (Blueprint $table): void {
            $table->dropColumn('safe_metadata');
        });
    }
};
