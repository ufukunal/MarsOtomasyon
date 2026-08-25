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
                'key' => 'products.view',
                'name' => 'Ürün görüntüleme',
                'description' => 'Aktif şirketteki ürün ve katalog kayıtlarını listeleme ve görüntüleme yetkisi.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'products.manage',
                'name' => 'Ürün yönetimi',
                'description' => 'Aktif şirkette ürün oluşturma, düzenleme ve lifecycle yönetimi yetkisi.',
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
                ->whereIn('key', ['products.view', 'products.manage'])
                ->pluck('id')
                ->all(),
        );

        if ($permissionIds !== []) {
            DB::table('role_permissions')
                ->whereIn('permission_id', $permissionIds)
                ->delete();
        }

        DB::table('permissions')
            ->whereIn('key', ['products.view', 'products.manage'])
            ->delete();
    }
};
