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
                'key' => 'crm.view',
                'name' => 'CRM görüntüleme',
                'description' => 'Aktif şirkette lead, fırsat ve CRM aktivitelerini görüntüleme yetkisi.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'crm.manage',
                'name' => 'CRM yönetimi',
                'description' => 'Aktif şirkette lead, fırsat, aktivite, takip ve dönüşüm yönetimi yetkisi.',
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
                ->whereIn('key', ['crm.view', 'crm.manage'])
                ->pluck('id')
                ->all(),
        );

        if ($permissionIds !== []) {
            DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
        }

        DB::table('permissions')->whereIn('key', ['crm.view', 'crm.manage'])->delete();
    }
};
