<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('storage_disk', 32)->default('local');
            $table->string('storage_key', 255)->unique();
            $table->string('original_name', 255);
            $table->string('mime_type', 160);
            $table->string('client_extension', 24)->nullable();
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->timestampTz('archived_at')->nullable();
            $table->foreignId('archived_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->unique(['id', 'company_id'], 'file_assets_id_company_unique');
            $table->index(['company_id', 'sha256']);
            $table->index(['company_id', 'archived_at']);
        });

        DB::statement("ALTER TABLE file_assets ADD CONSTRAINT file_assets_storage_disk_check CHECK (storage_disk = 'local')");
        DB::statement('ALTER TABLE file_assets ADD CONSTRAINT file_assets_size_check CHECK (size_bytes > 0 AND size_bytes <= 104857600)');
        DB::statement("ALTER TABLE file_assets ADD CONSTRAINT file_assets_sha256_check CHECK (sha256 ~ '^[0-9a-f]{64}$')");

        Schema::create('attachments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('file_asset_id');
            $table->string('attachable_type', 64);
            $table->unsignedBigInteger('attachable_id');
            $table->string('label', 160)->nullable();
            $table->foreignId('attached_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('attached_at');
            $table->timestampTz('detached_at')->nullable();
            $table->foreignId('detached_by_user_id')->nullable()->constrained('users')->restrictOnDelete();

            $table->index(['company_id', 'attachable_type', 'attachable_id'], 'attachments_target_index');
            $table->index(['file_asset_id', 'detached_at']);

            $table->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
            $table->foreign(
                ['file_asset_id', 'company_id'],
                'attachments_file_asset_company_fk',
            )
                ->references(['id', 'company_id'])
                ->on('file_assets')
                ->restrictOnDelete();
        });

        DB::statement('CREATE UNIQUE INDEX attachments_active_target_unique ON attachments (company_id, file_asset_id, attachable_type, attachable_id) WHERE detached_at IS NULL');

        $timestamp = now();
        DB::table('permissions')->insertOrIgnore([
            ['key' => 'core.file.view', 'name' => 'Dosya görüntüleme', 'description' => null, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['key' => 'core.file.manage', 'name' => 'Dosya yönetimi', 'description' => null, 'created_at' => $timestamp, 'updated_at' => $timestamp],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('file_assets');

        $permissionIds = DB::table('permissions')
            ->whereIn('key', ['core.file.view', 'core.file.manage'])
            ->pluck('id');

        DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }
};
