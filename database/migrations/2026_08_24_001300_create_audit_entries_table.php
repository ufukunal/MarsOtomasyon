<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_entries', function (Blueprint $table): void {
            $table->id();
            $table->ulid('event_id')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('correlation_id', 64);
            $table->string('source', 16)->default('web');
            $table->string('action', 100);
            $table->string('target_type', 64);
            $table->string('target_id', 64)->nullable();
            $table->jsonb('before_state')->nullable();
            $table->jsonb('after_state')->nullable();
            $table->jsonb('metadata');
            $table->timestampTz('occurred_at');
            $table->timestampTz('created_at');

            $table->index(['company_id', 'occurred_at']);
            $table->index(['actor_user_id', 'occurred_at']);
            $table->index(['target_type', 'target_id']);
        });

        DB::statement("ALTER TABLE audit_entries ALTER COLUMN metadata SET DEFAULT '{}'::jsonb");
        DB::statement("ALTER TABLE audit_entries ADD CONSTRAINT audit_entries_source_check CHECK (source IN ('web', 'api', 'job', 'console', 'system'))");
        DB::statement(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_prevent_audit_entry_mutation()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    RAISE EXCEPTION 'audit_entries are immutable' USING ERRCODE = '55000';
END;
$$
SQL);
        DB::statement('CREATE TRIGGER audit_entries_immutable BEFORE UPDATE OR DELETE ON audit_entries FOR EACH ROW EXECUTE FUNCTION mars_prevent_audit_entry_mutation()');
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_entries');
        DB::statement('DROP FUNCTION IF EXISTS mars_prevent_audit_entry_mutation()');
    }
};
