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
                'key' => 'quotes.view',
                'name' => 'Teklif görüntüleme',
                'description' => 'Aktif şirkette teklifleri ve teklif satırlarını görüntüleme yetkisi.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'quotes.manage',
                'name' => 'Teklif yönetimi',
                'description' => 'Aktif şirkette taslak teklif oluşturma, güncelleme ve iptal etme yetkisi.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        $permissionIds = array_map(
            'intval',
            DB::table('permissions')->whereIn('key', ['quotes.view', 'quotes.manage'])->pluck('id')->all(),
        );

        if ($permissionIds !== []) {
            DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
        }

        DB::table('permissions')->whereIn('key', ['quotes.view', 'quotes.manage'])->delete();
    }
};
