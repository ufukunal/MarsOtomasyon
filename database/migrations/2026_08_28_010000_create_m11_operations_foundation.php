<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('permissions')->insert([
            ['key' => 'integrations.view', 'name' => 'Entegrasyon görüntüleme', 'description' => 'Kanal bağlantıları ve senkronizasyon durumlarını görüntüleme.', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'integrations.manage', 'name' => 'Entegrasyon yönetimi', 'description' => 'Kanal bağlantıları, webhook ve tekrar denemelerini yönetme.', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'notifications.view', 'name' => 'Bildirim görüntüleme', 'description' => 'Bildirim teslimat durumlarını görüntüleme.', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'notifications.manage', 'name' => 'Bildirim yönetimi', 'description' => 'Bildirim şablonları ve teslimatlarını yönetme.', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'automation.view', 'name' => 'Otomasyon görüntüleme', 'description' => 'Otomasyon kuralları ve çalışma geçmişini görüntüleme.', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'automation.manage', 'name' => 'Otomasyon yönetimi', 'description' => 'Otomasyon kuralı, onay ve yürütmeyi yönetme.', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'operations.view', 'name' => 'Operasyon merkezi görüntüleme', 'description' => 'Queue, scheduler ve sağlık durumunu görüntüleme.', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'operations.manage', 'name' => 'Operasyon merkezi yönetimi', 'description' => 'Retry, prune ve heartbeat işlemlerini yönetme.', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'backups.view', 'name' => 'Yedek görüntüleme', 'description' => 'Yedek ve doğrulama geçmişini görüntüleme.', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'backups.manage', 'name' => 'Yedek yönetimi', 'description' => 'Şifreli yedek ve güvenli restore işlemlerini yönetme.', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'security.view', 'name' => 'Güvenlik merkezi görüntüleme', 'description' => 'Güvenlik olayları ve IP kurallarını görüntüleme.', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'security.manage', 'name' => 'Güvenlik merkezi yönetimi', 'description' => 'IP kuralları ve güvenlik operasyonlarını yönetme.', 'created_at' => $now, 'updated_at' => $now],
        ]);

        Schema::create('integration_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('provider', 32);
            $table->string('name', 96);
            $table->string('status', 16)->default('active');
            $table->string('base_url', 512)->nullable();
            $table->text('credentials_ciphertext')->nullable();
            $table->text('webhook_secret_ciphertext')->nullable();
            $table->timestampTz('last_sync_at')->nullable();
            $table->timestampTz('last_success_at')->nullable();
            $table->timestampTz('last_error_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestampsTz();
            $table->unique(['company_id', 'id']);
            $table->unique(['company_id', 'provider', 'name']);
            $table->index(['company_id', 'provider', 'status']);
        });

        Schema::create('integration_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('connection_id');
            $table->string('external_event_id', 160);
            $table->string('event_type', 96);
            $table->char('payload_sha256', 64);
            $table->jsonb('payload');
            $table->string('status', 16)->default('received');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestampTz('available_at');
            $table->timestampTz('processed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestampsTz();
            $table->foreign(['company_id', 'connection_id'])->references(['company_id', 'id'])->on('integration_connections')->restrictOnDelete();
            $table->unique(['company_id', 'connection_id', 'external_event_id']);
            $table->index(['status', 'available_at']);
        });

        Schema::create('integration_sync_effects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('connection_id');
            $table->uuid('operation_key');
            $table->string('operation', 32);
            $table->string('entity_type', 64);
            $table->string('entity_id', 160);
            $table->char('payload_sha256', 64);
            $table->jsonb('payload');
            $table->string('status', 16)->default('queued');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestampTz('available_at');
            $table->timestampTz('completed_at')->nullable();
            $table->string('external_id', 160)->nullable();
            $table->text('last_error')->nullable();
            $table->timestampsTz();
            $table->foreign(['company_id', 'connection_id'])->references(['company_id', 'id'])->on('integration_connections')->restrictOnDelete();
            $table->unique(['company_id', 'operation_key']);
            $table->index(['status', 'available_at']);
        });

        Schema::create('notification_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('key', 96);
            $table->string('channel', 16);
            $table->string('name', 160);
            $table->string('status', 16)->default('active');
            $table->string('subject')->nullable();
            $table->text('body');
            $table->timestampsTz();
            $table->unique(['company_id', 'id']);
            $table->unique(['company_id', 'key', 'channel']);
        });

        Schema::create('notification_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('template_id')->nullable();
            $table->uuid('idempotency_key');
            $table->string('channel', 16);
            $table->string('recipient', 320);
            $table->string('subject')->nullable();
            $table->text('body');
            $table->jsonb('context')->nullable();
            $table->string('provider', 48)->nullable();
            $table->string('provider_message_id', 192)->nullable();
            $table->string('status', 16)->default('queued');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestampTz('available_at');
            $table->timestampTz('sent_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestampsTz();
            $table->foreign(['company_id', 'template_id'])->references(['company_id', 'id'])->on('notification_templates')->restrictOnDelete();
            $table->unique(['company_id', 'idempotency_key']);
            $table->index(['status', 'available_at']);
        });

        Schema::create('automation_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('key', 96);
            $table->string('name', 160);
            $table->string('event_type', 96);
            $table->jsonb('conditions')->nullable();
            $table->string('action_type', 32);
            $table->jsonb('action_payload');
            $table->boolean('requires_approval')->default(false);
            $table->boolean('is_enabled')->default(true);
            $table->smallInteger('priority')->default(100);
            $table->timestampsTz();
            $table->unique(['company_id', 'id']);
            $table->unique(['company_id', 'key']);
            $table->index(['company_id', 'event_type', 'is_enabled', 'priority']);
        });

        Schema::create('automation_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('rule_id');
            $table->string('trigger_key', 160);
            $table->string('status', 24);
            $table->jsonb('input');
            $table->jsonb('output')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestampsTz();
            $table->foreign(['company_id', 'rule_id'])->references(['company_id', 'id'])->on('automation_rules')->restrictOnDelete();
            $table->unique(['company_id', 'rule_id', 'trigger_key']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('operations_heartbeats', function (Blueprint $table): void {
            $table->id();
            $table->string('component', 32);
            $table->string('instance', 128);
            $table->timestampTz('last_seen_at');
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
            $table->unique(['component', 'instance']);
            $table->index(['component', 'last_seen_at']);
        });

        Schema::create('scheduler_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('task_key', 128);
            $table->string('status', 16);
            $table->timestampTz('started_at');
            $table->timestampTz('finished_at')->nullable();
            $table->jsonb('details')->nullable();
            $table->timestampsTz();
            $table->index(['task_key', 'started_at']);
        });

        Schema::create('operations_metrics', function (Blueprint $table): void {
            $table->id();
            $table->timestampTz('captured_at')->index();
            $table->boolean('database_ok');
            $table->boolean('valkey_ok');
            $table->unsignedBigInteger('queue_depth')->default(0);
            $table->unsignedBigInteger('failed_jobs')->default(0);
            $table->unsignedBigInteger('integration_pending')->default(0);
            $table->unsignedBigInteger('notification_pending')->default(0);
            $table->unsignedBigInteger('automation_pending')->default(0);
            $table->jsonb('details')->nullable();
        });

        Schema::create('backup_artifacts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('status', 16);
            $table->string('disk', 64);
            $table->string('path', 512);
            $table->char('sha256', 64)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->boolean('encrypted')->default(true);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('verified_at')->nullable();
            $table->timestampTz('restore_started_at')->nullable();
            $table->timestampTz('restore_finished_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestampsTz();
            $table->unique(['disk', 'path']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('security_ip_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('action', 8);
            $table->string('cidr', 64);
            $table->string('label', 160)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampsTz();
            $table->unique(['company_id', 'action', 'cidr']);
        });

        Schema::create('security_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('event_type', 96);
            $table->string('severity', 16);
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->jsonb('context')->nullable();
            $table->timestampTz('created_at');
            $table->index(['company_id', 'severity', 'created_at']);
        });

        if (! Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }

        if (! Schema::hasTable('job_batches')) {
            Schema::create('job_batches', function (Blueprint $table): void {
                $table->string('id')->primary();
                $table->string('name');
                $table->integer('total_jobs');
                $table->integer('pending_jobs');
                $table->integer('failed_jobs');
                $table->longText('failed_job_ids');
                $table->mediumText('options')->nullable();
                $table->integer('cancelled_at')->nullable();
                $table->integer('created_at');
                $table->integer('finished_at')->nullable();
            });
        }

        if (! Schema::hasTable('failed_jobs')) {
            Schema::create('failed_jobs', function (Blueprint $table): void {
                $table->id();
                $table->string('uuid')->unique();
                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();
            });
        }

        DB::statement("ALTER TABLE integration_connections ADD CONSTRAINT m11_connection_provider CHECK (provider IN ('woocommerce','trendyol')), ADD CONSTRAINT m11_connection_status CHECK (status IN ('active','paused','disabled'))");
        DB::statement("ALTER TABLE integration_events ADD CONSTRAINT m11_event_status CHECK (status IN ('received','processing','processed','failed','ignored')), ADD CONSTRAINT m11_event_hash CHECK (payload_sha256 ~ '^[0-9a-f]{64}$')");
        DB::statement("ALTER TABLE integration_sync_effects ADD CONSTRAINT m11_sync_operation CHECK (operation IN ('order','product','price','stock','invoice','refund')), ADD CONSTRAINT m11_sync_status CHECK (status IN ('queued','sending','succeeded','failed','ignored')), ADD CONSTRAINT m11_sync_hash CHECK (payload_sha256 ~ '^[0-9a-f]{64}$')");
        DB::statement("ALTER TABLE notification_templates ADD CONSTRAINT m11_template_channel CHECK (channel IN ('email','sms','whatsapp')), ADD CONSTRAINT m11_template_status CHECK (status IN ('active','inactive'))");
        DB::statement("ALTER TABLE notification_deliveries ADD CONSTRAINT m11_delivery_channel CHECK (channel IN ('email','sms','whatsapp')), ADD CONSTRAINT m11_delivery_status CHECK (status IN ('queued','sending','sent','failed','cancelled'))");
        DB::statement("ALTER TABLE automation_rules ADD CONSTRAINT m11_rule_action CHECK (action_type IN ('notify','integration_sync','security_event'))");
        DB::statement("ALTER TABLE automation_runs ADD CONSTRAINT m11_run_status CHECK (status IN ('pending_approval','queued','approved','running','succeeded','failed','rejected'))");
        DB::statement("ALTER TABLE operations_heartbeats ADD CONSTRAINT m11_heartbeat_component CHECK (component IN ('worker','scheduler'))");
        DB::statement("ALTER TABLE scheduler_runs ADD CONSTRAINT m11_scheduler_status CHECK (status IN ('running','succeeded','failed','skipped'))");
        DB::statement("ALTER TABLE backup_artifacts ADD CONSTRAINT m11_backup_status CHECK (status IN ('creating','ready','failed','restoring','restored')), ADD CONSTRAINT m11_backup_hash CHECK (sha256 IS NULL OR sha256 ~ '^[0-9a-f]{64}$')");
        DB::statement("ALTER TABLE security_ip_rules ADD CONSTRAINT m11_ip_action CHECK (action IN ('allow','deny'))");
        DB::statement("ALTER TABLE security_events ADD CONSTRAINT m11_security_severity CHECK (severity IN ('info','warning','critical'))");

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_m11_guard_integration_event() RETURNS trigger AS $$
BEGIN
  IF TG_OP = 'DELETE' THEN RAISE EXCEPTION 'integration events cannot be deleted' USING ERRCODE='55000'; END IF;
  IF NEW.company_id IS DISTINCT FROM OLD.company_id OR NEW.connection_id IS DISTINCT FROM OLD.connection_id OR NEW.external_event_id IS DISTINCT FROM OLD.external_event_id OR NEW.event_type IS DISTINCT FROM OLD.event_type OR NEW.payload_sha256 IS DISTINCT FROM OLD.payload_sha256 OR NEW.payload IS DISTINCT FROM OLD.payload OR NEW.created_at IS DISTINCT FROM OLD.created_at THEN RAISE EXCEPTION 'integration event identity and payload are immutable' USING ERRCODE='55000'; END IF;
  RETURN NEW;
END; $$ LANGUAGE plpgsql;
CREATE TRIGGER m11_integration_event_guard BEFORE UPDATE OR DELETE ON integration_events FOR EACH ROW EXECUTE FUNCTION mars_m11_guard_integration_event();

CREATE OR REPLACE FUNCTION mars_m11_guard_notification_delivery() RETURNS trigger AS $$
BEGIN
  IF TG_OP = 'DELETE' THEN RAISE EXCEPTION 'notification deliveries cannot be deleted' USING ERRCODE='55000'; END IF;
  IF NEW.company_id IS DISTINCT FROM OLD.company_id OR NEW.template_id IS DISTINCT FROM OLD.template_id OR NEW.idempotency_key IS DISTINCT FROM OLD.idempotency_key OR NEW.channel IS DISTINCT FROM OLD.channel OR NEW.recipient IS DISTINCT FROM OLD.recipient OR NEW.subject IS DISTINCT FROM OLD.subject OR NEW.body IS DISTINCT FROM OLD.body OR NEW.context IS DISTINCT FROM OLD.context OR NEW.created_at IS DISTINCT FROM OLD.created_at THEN RAISE EXCEPTION 'notification delivery content is immutable' USING ERRCODE='55000'; END IF;
  RETURN NEW;
END; $$ LANGUAGE plpgsql;
CREATE TRIGGER m11_notification_delivery_guard BEFORE UPDATE OR DELETE ON notification_deliveries FOR EACH ROW EXECUTE FUNCTION mars_m11_guard_notification_delivery();

CREATE OR REPLACE FUNCTION mars_m11_guard_automation_run() RETURNS trigger AS $$
BEGIN
  IF TG_OP = 'DELETE' THEN RAISE EXCEPTION 'automation runs cannot be deleted' USING ERRCODE='55000'; END IF;
  IF NEW.company_id IS DISTINCT FROM OLD.company_id OR NEW.rule_id IS DISTINCT FROM OLD.rule_id OR NEW.trigger_key IS DISTINCT FROM OLD.trigger_key OR NEW.input IS DISTINCT FROM OLD.input OR NEW.created_at IS DISTINCT FROM OLD.created_at THEN RAISE EXCEPTION 'automation run trigger identity is immutable' USING ERRCODE='55000'; END IF;
  RETURN NEW;
END; $$ LANGUAGE plpgsql;
CREATE TRIGGER m11_automation_run_guard BEFORE UPDATE OR DELETE ON automation_runs FOR EACH ROW EXECUTE FUNCTION mars_m11_guard_automation_run();

CREATE OR REPLACE FUNCTION mars_m11_security_append_only() RETURNS trigger AS $$
BEGIN RAISE EXCEPTION 'security events are append-only' USING ERRCODE='55000'; END; $$ LANGUAGE plpgsql;
CREATE TRIGGER m11_security_event_guard BEFORE UPDATE OR DELETE ON security_events FOR EACH ROW EXECUTE FUNCTION mars_m11_security_append_only();
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS mars_m11_guard_integration_event() CASCADE; DROP FUNCTION IF EXISTS mars_m11_guard_notification_delivery() CASCADE; DROP FUNCTION IF EXISTS mars_m11_guard_automation_run() CASCADE; DROP FUNCTION IF EXISTS mars_m11_security_append_only() CASCADE;');
        foreach (['failed_jobs','job_batches','jobs','security_events','security_ip_rules','backup_artifacts','operations_metrics','scheduler_runs','operations_heartbeats','automation_runs','automation_rules','notification_deliveries','notification_templates','integration_sync_effects','integration_events','integration_connections'] as $table) {
            Schema::dropIfExists($table);
        }
        DB::table('permissions')->whereIn('key', ['integrations.view','integrations.manage','notifications.view','notifications.manage','automation.view','automation.manage','operations.view','operations.manage','backups.view','backups.manage','security.view','security.manage'])->delete();
    }
};
