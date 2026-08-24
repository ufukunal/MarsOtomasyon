<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->unique(['id', 'company_id'], 'branches_id_company_unique');
        });

        $timestamp = now();

        DB::table('permissions')->insertOrIgnore([
            ['key' => 'core.branch.view', 'name' => 'Şube görüntüleme', 'description' => null, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['key' => 'core.branch.manage', 'name' => 'Şube yönetimi', 'description' => null, 'created_at' => $timestamp, 'updated_at' => $timestamp],
        ]);
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('key', ['core.branch.view', 'core.branch.manage'])
            ->pluck('id');

        DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();

        Schema::table('branches', function (Blueprint $table): void {
            $table->dropUnique('branches_id_company_unique');
        });
    }
};
