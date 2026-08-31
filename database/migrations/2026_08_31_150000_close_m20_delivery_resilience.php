<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->uuid('idempotency_key');
            $table->unsignedBigInteger('template_id')->nullable();
            $table->unsignedInteger('template_version')->nullable();
            $table->string('subject')->nullable();
            $table->text('body');
            $table->jsonb('context')->nullable();
            $table->timestampsTz();
            $table->unique(['company_id', 'id']);
            $table->unique(['company_id', 'idempotency_key']);
            $table->foreign(['company_id', 'template_id'])->references(['company_id', 'id'])->on('notification_templates')->restrictOnDelete();
        });

        DB::statement(<<<'SQL'
INSERT INTO notifications (company_id, idempotency_key, template_id, template_version, subject, body, context, created_at, updated_at)
SELECT company_id, idempotency_key, template_id, template_version, subject, body, context, created_at, updated_at
FROM notification_deliveries
ON CONFLICT (company_id, idempotency_key) DO NOTHING
SQL);

        Schema::table('notification_deliveries', function (Blueprint $table): void {
            $table->unsignedBigInteger('notification_id')->nullable();
            $table->unsignedSmallInteger('dispatch_attempts')->default(0);
            $table->string('failure_class', 64)->nullable();
            $table->boolean('manual_retry_required')->default(false);
            $table->timestampTz('last_attempt_at')->nullable();
        });

        DB::statement(<<<'SQL'
UPDATE notification_deliveries AS delivery
SET notification_id = notification.id
FROM notifications AS notification
WHERE notification.company_id = delivery.company_id
  AND notification.idempotency_key = delivery.idempotency_key
SQL);
        DB::statement('ALTER TABLE notification_deliveries ALTER COLUMN notification_id SET NOT NULL');

        Schema::table('notification_deliveries', function (Blueprint $table): void {
            $table->foreign(['company_id', 'notification_id'], 'notification_deliveries_notification_foreign')
                ->references(['company_id', 'id'])
                ->on('notifications')
                ->restrictOnDelete();
            $table->index(['company_id', 'status', 'manual_retry_required'], 'notification_deliveries_attention_index');
        });

        Schema::table('notification_provider_attempts', function (Blueprint $table): void {
            $table->boolean('retryable')->default(false);
            $table->string('failure_class', 64)->nullable();
        });

        DB::statement('ALTER TABLE notification_deliveries DROP CONSTRAINT IF EXISTS m11_delivery_status');
        DB::statement("ALTER TABLE notification_deliveries ADD CONSTRAINT m11_delivery_status CHECK (status IN ('queued','sending','sent','failed','ambiguous','cancelled'))");
        DB::statement('ALTER TABLE notification_provider_attempts DROP CONSTRAINT IF EXISTS m20_provider_attempt_status_check');
        DB::statement("ALTER TABLE notification_provider_attempts ADD CONSTRAINT m20_provider_attempt_status_check CHECK (status IN ('sending','succeeded','failed','ambiguous'))");

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_m20_notifications_immutable() RETURNS trigger AS $$
BEGIN
  RAISE EXCEPTION 'notifications are immutable' USING ERRCODE='55000';
END; $$ LANGUAGE plpgsql;
CREATE TRIGGER m20_notifications_immutable BEFORE UPDATE OR DELETE ON notifications FOR EACH ROW EXECUTE FUNCTION mars_m20_notifications_immutable();

CREATE OR REPLACE FUNCTION mars_m11_guard_notification_delivery() RETURNS trigger AS $$
BEGIN
  IF TG_OP = 'DELETE' THEN RAISE EXCEPTION 'notification deliveries cannot be deleted' USING ERRCODE='55000'; END IF;
  IF NEW.company_id IS DISTINCT FROM OLD.company_id OR NEW.notification_id IS DISTINCT FROM OLD.notification_id OR NEW.template_id IS DISTINCT FROM OLD.template_id OR NEW.template_version IS DISTINCT FROM OLD.template_version OR NEW.idempotency_key IS DISTINCT FROM OLD.idempotency_key OR NEW.channel IS DISTINCT FROM OLD.channel OR NEW.recipient IS DISTINCT FROM OLD.recipient OR NEW.subject IS DISTINCT FROM OLD.subject OR NEW.body IS DISTINCT FROM OLD.body OR NEW.context IS DISTINCT FROM OLD.context OR NEW.created_at IS DISTINCT FROM OLD.created_at THEN RAISE EXCEPTION 'notification delivery content is immutable' USING ERRCODE='55000'; END IF;
  RETURN NEW;
END; $$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION mars_m20_guard_notification_provider_attempt() RETURNS trigger AS $$
BEGIN
  IF TG_OP = 'DELETE' THEN RAISE EXCEPTION 'notification provider attempts cannot be deleted' USING ERRCODE='55000'; END IF;
  IF OLD.status IN ('succeeded','failed','ambiguous') THEN RAISE EXCEPTION 'finalized notification provider attempts are immutable' USING ERRCODE='55000'; END IF;
  IF NEW.company_id IS DISTINCT FROM OLD.company_id OR NEW.delivery_id IS DISTINCT FROM OLD.delivery_id OR NEW.attempt_no IS DISTINCT FROM OLD.attempt_no OR NEW.provider IS DISTINCT FROM OLD.provider OR NEW.request_meta IS DISTINCT FROM OLD.request_meta OR NEW.started_at IS DISTINCT FROM OLD.started_at THEN RAISE EXCEPTION 'notification provider attempt identity is immutable' USING ERRCODE='55000'; END IF;
  IF OLD.status = 'sending' AND NEW.status NOT IN ('succeeded','failed','ambiguous') THEN RAISE EXCEPTION 'notification provider attempt transition is invalid' USING ERRCODE='55000'; END IF;
  RETURN NEW;
END; $$ LANGUAGE plpgsql;
CREATE TRIGGER m20_notification_provider_attempt_guard BEFORE UPDATE OR DELETE ON notification_provider_attempts FOR EACH ROW EXECUTE FUNCTION mars_m20_guard_notification_provider_attempt();
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS m20_notification_provider_attempt_guard ON notification_provider_attempts; DROP FUNCTION IF EXISTS mars_m20_guard_notification_provider_attempt(); DROP TRIGGER IF EXISTS m20_notifications_immutable ON notifications; DROP FUNCTION IF EXISTS mars_m20_notifications_immutable();');

        DB::table('notification_provider_attempts')->where('status', 'ambiguous')->update(['status' => 'failed']);
        DB::table('notification_deliveries')->where('status', 'ambiguous')->update(['status' => 'failed']);
        DB::statement('ALTER TABLE notification_provider_attempts DROP CONSTRAINT IF EXISTS m20_provider_attempt_status_check');
        DB::statement("ALTER TABLE notification_provider_attempts ADD CONSTRAINT m20_provider_attempt_status_check CHECK (status IN ('sending','succeeded','failed'))");
        DB::statement('ALTER TABLE notification_deliveries DROP CONSTRAINT IF EXISTS m11_delivery_status');
        DB::statement("ALTER TABLE notification_deliveries ADD CONSTRAINT m11_delivery_status CHECK (status IN ('queued','sending','sent','failed','cancelled'))");

        Schema::table('notification_provider_attempts', function (Blueprint $table): void {
            $table->dropColumn(['retryable', 'failure_class']);
        });
        Schema::table('notification_deliveries', function (Blueprint $table): void {
            $table->dropIndex('notification_deliveries_attention_index');
            $table->dropForeign('notification_deliveries_notification_foreign');
            $table->dropColumn(['notification_id', 'dispatch_attempts', 'failure_class', 'manual_retry_required', 'last_attempt_at']);
        });
        Schema::dropIfExists('notifications');

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_m11_guard_notification_delivery() RETURNS trigger AS $$
BEGIN
  IF TG_OP = 'DELETE' THEN RAISE EXCEPTION 'notification deliveries cannot be deleted' USING ERRCODE='55000'; END IF;
  IF NEW.company_id IS DISTINCT FROM OLD.company_id OR NEW.template_id IS DISTINCT FROM OLD.template_id OR NEW.idempotency_key IS DISTINCT FROM OLD.idempotency_key OR NEW.channel IS DISTINCT FROM OLD.channel OR NEW.recipient IS DISTINCT FROM OLD.recipient OR NEW.subject IS DISTINCT FROM OLD.subject OR NEW.body IS DISTINCT FROM OLD.body OR NEW.context IS DISTINCT FROM OLD.context OR NEW.created_at IS DISTINCT FROM OLD.created_at THEN RAISE EXCEPTION 'notification delivery content is immutable' USING ERRCODE='55000'; END IF;
  RETURN NEW;
END; $$ LANGUAGE plpgsql;
SQL);
    }
};
