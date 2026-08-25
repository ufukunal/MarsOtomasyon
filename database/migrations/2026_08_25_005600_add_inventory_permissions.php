<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('permissions')->insert([
            [
                'key' => 'inventory.view',
                'name' => 'Stok ve depo görüntüleme',
                'description' => 'Aktif şirkette stok bakiyeleri, hareketler, depolar ve lokasyonları görüntüleme yetkisi.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'inventory.manage',
                'name' => 'Stok ve depo yönetimi',
                'description' => 'Aktif şirkette depo/lokasyon oluşturma ve yetkili stok hareketi işleme yetkisi.',
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
                ->whereIn('key', ['inventory.view', 'inventory.manage'])
                ->pluck('id')
                ->all(),
        );

        if ($permissionIds !== []) {
            DB::table('role_permissions')
                ->whereIn('permission_id', $permissionIds)
                ->delete();
        }

        DB::table('permissions')
            ->whereIn('key', ['inventory.view', 'inventory.manage'])
            ->delete();
    }
};
