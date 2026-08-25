<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

if (! class_exists('AddAccountPermissions20260825000200', false)) {
    final class AddAccountPermissions20260825000200 extends Migration
    {
        public function up(): void
        {
            $now = now();

            DB::table('permissions')->insert([
                [
                    'key' => 'accounts.view',
                    'name' => 'Cari görüntüleme',
                    'description' => 'Aktif şirketteki cari kayıtlarını listeleme ve görüntüleme yetkisi.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'key' => 'accounts.manage',
                    'name' => 'Cari yönetimi',
                    'description' => 'Aktif şirkette cari oluşturma, düzenleme ve lifecycle yönetimi yetkisi.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
        }

        public function down(): void
        {
            $permissionIds = array_map(
                'intval',
                DB::table('permissions')
                    ->whereIn('key', ['accounts.view', 'accounts.manage'])
                    ->pluck('id')
                    ->all(),
            );

            if ($permissionIds !== []) {
                DB::table('role_permissions')
                    ->whereIn('permission_id', $permissionIds)
                    ->delete();
            }

            DB::table('permissions')
                ->whereIn('key', ['accounts.view', 'accounts.manage'])
                ->delete();
        }
    }
}

return new AddAccountPermissions20260825000200;
