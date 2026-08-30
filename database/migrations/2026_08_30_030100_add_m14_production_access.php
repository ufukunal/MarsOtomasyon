<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        DB::table('permissions')->insertOrIgnore([
            [
                'key' => 'production.view',
                'name' => 'Üretim görüntüleme',
                'description' => 'Reçete, üretim emri, maliyet, fire/eksik, teknik dosya ve üretim raporlarını görüntüleme yetkisi.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'production.manage',
                'name' => 'Üretim yönetimi',
                'description' => 'Reçete ve üretim emri oluşturma, malzeme çıkışı, fire/eksik, mamul girişi, tamamlama ve teknik dosya yönetimi yetkisi.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->whereIn('key', ['production.view', 'production.manage'])->pluck('id');
        DB::table('role_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
