<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_files', function (Blueprint $table): void {
            $table->boolean('is_main')->default(false);
            $table->json('destinations')->nullable();
            $table->json('transform_metadata')->nullable();
            $table->json('provider_validation')->nullable();
            $table->index(['company_id', 'product_id', 'kind', 'is_main'], 'product_files_main_lookup_index');
        });

        DB::statement("CREATE UNIQUE INDEX product_files_one_main_media_unique ON product_files (company_id, product_id) WHERE kind = 'media' AND is_main = TRUE");

        Schema::table('file_assets', function (Blueprint $table): void {
            $table->timestampTz('quarantined_at')->nullable();
            $table->foreignId('quarantined_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('quarantine_reason', 255)->nullable();
            $table->index(['company_id', 'quarantined_at'], 'file_assets_quarantine_index');
        });
    }

    public function down(): void
    {
        Schema::table('file_assets', function (Blueprint $table): void {
            $table->dropForeign(['quarantined_by_user_id']);
            $table->dropIndex('file_assets_quarantine_index');
            $table->dropColumn(['quarantined_at', 'quarantined_by_user_id', 'quarantine_reason']);
        });

        DB::statement('DROP INDEX IF EXISTS product_files_one_main_media_unique');

        Schema::table('product_files', function (Blueprint $table): void {
            $table->dropIndex('product_files_main_lookup_index');
            $table->dropColumn(['is_main', 'destinations', 'transform_metadata', 'provider_validation']);
        });
    }
};
