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
            $table->jsonb('destinations')->nullable();
            $table->jsonb('transform_metadata')->nullable();
            $table->jsonb('provider_validation')->nullable();
            $table->index(['company_id', 'product_id', 'kind', 'is_main'], 'product_files_main_lookup_index');
        });

        DB::statement("CREATE UNIQUE INDEX product_files_one_main_media_unique ON product_files (company_id, product_id) WHERE kind = 'media' AND is_main = TRUE");
        DB::statement("ALTER TABLE product_files ADD CONSTRAINT product_files_image_metadata_media_only_check CHECK (kind = 'media' OR (is_main = FALSE AND destinations IS NULL AND transform_metadata IS NULL AND provider_validation IS NULL))");

        Schema::table('file_assets', function (Blueprint $table): void {
            $table->timestampTz('quarantined_at')->nullable();
            $table->foreignId('quarantined_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('quarantine_reason', 255)->nullable();
            $table->index(['company_id', 'quarantined_at'], 'file_assets_quarantine_index');
        });

        DB::statement('ALTER TABLE file_assets ADD CONSTRAINT file_assets_quarantine_state_check CHECK ((quarantined_at IS NULL AND quarantined_by_user_id IS NULL AND quarantine_reason IS NULL) OR (quarantined_at IS NOT NULL AND quarantined_by_user_id IS NOT NULL AND quarantine_reason IS NOT NULL))');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE file_assets DROP CONSTRAINT IF EXISTS file_assets_quarantine_state_check');

        Schema::table('file_assets', function (Blueprint $table): void {
            $table->dropForeign(['quarantined_by_user_id']);
            $table->dropIndex('file_assets_quarantine_index');
            $table->dropColumn(['quarantined_at', 'quarantined_by_user_id', 'quarantine_reason']);
        });

        DB::statement('DROP INDEX IF EXISTS product_files_one_main_media_unique');
        DB::statement('ALTER TABLE product_files DROP CONSTRAINT IF EXISTS product_files_image_metadata_media_only_check');

        Schema::table('product_files', function (Blueprint $table): void {
            $table->dropIndex('product_files_main_lookup_index');
            $table->dropColumn(['is_main', 'destinations', 'transform_metadata', 'provider_validation']);
        });
    }
};
