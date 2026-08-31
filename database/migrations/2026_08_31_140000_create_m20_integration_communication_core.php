<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_integration_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('family', 32);
            $table->string('provider_key', 64)->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->string('verification_status', 32)->default('unverified');
            $table->string('endpoint_url', 512)->nullable();
            $table->jsonb('settings')->nullable();
            $table->text('credentials_ciphertext')->nullable();
            $table->char('credential_fingerprint', 64)->nullable();
            $table->timestampTz('last_validated_at')->nullable();
            $table->text('last_validation_error')->nullable();
            $table->timestampsTz();
            $table->unique(['company_id', 'family']);
        });

        DB::statement("ALTER TABLE system_integration_settings ADD CONSTRAINT m20_system_integration_family_check CHECK (family IN ('sms','email','whatsapp','e_document','scanner_agent'))");
        DB::statement("ALTER TABLE system_integration_settings ADD CONSTRAINT m20_system_integration_verification_check CHECK (verification_status IN ('unverified','configuration_validated','connection_tested'))");
        DB::statement("ALTER TABLE system_integration_settings ADD CONSTRAINT m20_system_integration_provider_shape_check CHECK (provider_key IS NULL OR provider_key ~ '^[a-z0-9]+(?:[._-][a-z0-9]+)*$')");
        DB::statement("ALTER TABLE system_integration_settings ADD CONSTRAINT m20_system_integration_fingerprint_check CHECK (credential_fingerprint IS NULL OR credential_fingerprint ~ '^[0-9a-f]{64}$')");

        Schema::table('notification_templates', function (Blueprint $table): void {
            $table->unsignedInteger('current_version')->default(1);
        });

        Schema::create('notification_template_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->unsignedBigInteger('template_id');
            $table->unsignedInteger('version');
            $table->string('name', 160);
            $table->string('subject')->nullable();
            $table->text('body');
            $table->jsonb('variables')->default('[]');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at');
            $table->foreign(['company_id', 'template_id'])->references(['company_id', 'id'])->on('notification_templates')->cascadeOnDelete();
            $table->unique(['company_id', 'template_id', 'version'], 'notification_template_versions_scope_unique');
        });

        DB::statement(<<<'SQL'
INSERT INTO notification_template_versions (company_id, template_id, version, name, subject, body, variables, created_by_user_id, created_at)
SELECT company_id, id, 1, name, subject, body, '[]'::jsonb, NULL, created_at
FROM notification_templates
SQL);

        Schema::table('notification_deliveries', function (Blueprint $table): void {
            $table->unsignedInteger('template_version')->nullable();
            $table->unique(['company_id', 'id'], 'notification_deliveries_company_id_id_unique');
        });

        Schema::create('notification_provider_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->unsignedBigInteger('delivery_id');
            $table->unsignedSmallInteger('attempt_no');
            $table->string('provider', 64);
            $table->string('status', 16)->default('sending');
            $table->jsonb('request_meta')->nullable();
            $table->jsonb('response_meta')->nullable();
            $table->text('error')->nullable();
            $table->timestampTz('started_at');
            $table->timestampTz('finished_at')->nullable();
            $table->foreign(['company_id', 'delivery_id'])->references(['company_id', 'id'])->on('notification_deliveries')->cascadeOnDelete();
            $table->unique(['company_id', 'delivery_id', 'attempt_no'], 'notification_provider_attempts_scope_unique');
            $table->index(['company_id', 'status', 'started_at'], 'notification_provider_attempts_status_index');
        });

        DB::statement("ALTER TABLE notification_provider_attempts ADD CONSTRAINT m20_provider_attempt_status_check CHECK (status IN ('sending','succeeded','failed'))");
        DB::statement(<<<'SQL'
CREATE OR REPLACE FUNCTION m20_guard_template_version() RETURNS trigger AS $$
BEGIN
    RAISE EXCEPTION 'notification template versions are immutable';
END;
$$ LANGUAGE plpgsql;
SQL);
        DB::statement('CREATE TRIGGER m20_notification_template_version_immutable BEFORE UPDATE OR DELETE ON notification_template_versions FOR EACH ROW EXECUTE FUNCTION m20_guard_template_version()');
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS m20_notification_template_version_immutable ON notification_template_versions');
        DB::statement('DROP FUNCTION IF EXISTS m20_guard_template_version()');
        Schema::dropIfExists('notification_provider_attempts');
        Schema::table('notification_deliveries', function (Blueprint $table): void {
            $table->dropUnique('notification_deliveries_company_id_id_unique');
            $table->dropColumn('template_version');
        });
        Schema::dropIfExists('notification_template_versions');
        Schema::table('notification_templates', function (Blueprint $table): void {
            $table->dropColumn('current_version');
        });
        Schema::dropIfExists('system_integration_settings');
    }
};
