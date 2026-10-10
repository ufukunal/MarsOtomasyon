<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('master')->table('company_user', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true);
            $table->jsonb('permission_overrides')->nullable();
        });
    }

    public function down(): void
    {
        // Never destroy company-level permissions during a rollback.
        throw new RuntimeException('DOC2 şirket yetkileri otomatik rollback ile silinemez.');
    }
};
