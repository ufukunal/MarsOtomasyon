<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The forward schema is already correct. This migration exists so a full
     * rollback removes role-permission edges before the M11 foundation removes
     * the corresponding permission rows.
     */
    public function up(): void {}

    public function down(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('role_permissions')) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('key', [
                'integrations.view',
                'integrations.manage',
                'notifications.view',
                'notifications.manage',
                'automation.view',
                'automation.manage',
                'operations.view',
                'operations.manage',
                'backups.view',
                'backups.manage',
                'security.view',
                'security.manage',
            ])
            ->pluck('id');

        if ($permissionIds->isNotEmpty()) {
            DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
        }
    }
};
