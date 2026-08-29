<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('permissions')->updateOrInsert(
            ['key' => 'reports.view'],
            [
                'name' => 'Raporları görüntüleme',
                'description' => 'Finans, yaşlandırma, stok değerleme ve hareket raporlarını görüntüleme ve dışa aktarma yetkisi.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->where('key', 'reports.view')
            ->pluck('id');

        DB::table('role_permissions')
            ->whereIn('permission_id', $permissionIds)
            ->delete();

        DB::table('permissions')
            ->where('key', 'reports.view')
            ->delete();
    }
};
