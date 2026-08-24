<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_memberships', function (Blueprint $table): void {
            $table->unique(['id', 'company_id'], 'company_memberships_id_company_unique');
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 120)->unique();
            $table->string('name', 160);
            $table->string('description', 255)->nullable();
            $table->timestampsTz();
        });

        $timestamp = now();

        DB::table('permissions')->insert([
            ['key' => 'core.company.view', 'name' => 'Şirket görüntüleme', 'description' => null, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['key' => 'core.company.manage', 'name' => 'Şirket yönetimi', 'description' => null, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['key' => 'core.user.view', 'name' => 'Kullanıcı görüntüleme', 'description' => null, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['key' => 'core.user.manage', 'name' => 'Kullanıcı yönetimi', 'description' => null, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['key' => 'core.role.view', 'name' => 'Rol ve yetki görüntüleme', 'description' => null, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['key' => 'core.role.manage', 'name' => 'Rol ve yetki yönetimi', 'description' => null, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['key' => 'core.settings.view', 'name' => 'Ayarları görüntüleme', 'description' => null, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['key' => 'core.settings.manage', 'name' => 'Ayarları yönetme', 'description' => null, 'created_at' => $timestamp, 'updated_at' => $timestamp],
        ]);

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('code', 64);
            $table->string('name', 160);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['id', 'company_id'], 'roles_id_company_unique');
        });

        DB::statement('CREATE UNIQUE INDEX roles_company_code_lower_unique ON roles (company_id, lower(code))');

        Schema::create('role_permissions', function (Blueprint $table): void {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->restrictOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create('company_membership_roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('membership_id');
            $table->unsignedBigInteger('role_id');
            $table->timestampTz('assigned_at')->useCurrent();

            $table->primary(['membership_id', 'role_id']);
            $table->index(['company_id', 'membership_id']);

            $table->foreign(
                ['membership_id', 'company_id'],
                'company_membership_roles_membership_company_fk',
            )
                ->references(['id', 'company_id'])
                ->on('company_memberships')
                ->cascadeOnDelete();

            $table->foreign(
                ['role_id', 'company_id'],
                'company_membership_roles_role_company_fk',
            )
                ->references(['id', 'company_id'])
                ->on('roles')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_membership_roles');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');

        Schema::table('company_memberships', function (Blueprint $table): void {
            $table->dropUnique('company_memberships_id_company_unique');
        });
    }
};
