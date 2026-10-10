<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('master')->create('company_database_registry', function (Blueprint $table): void {
            $table->foreignId('company_id')->primary()->constrained('companies')->restrictOnDelete();
            $table->string('database_name', 63)->unique();
            $table->string('state', 20)->default('planned');
            $table->string('schema_fingerprint', 128)->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Registry entries become data-integrity evidence. Never discard them on rollback.
        throw new RuntimeException('DOC2 kayıtları otomatik rollback ile silinemez; forward-fix kullanın.');
    }
};
