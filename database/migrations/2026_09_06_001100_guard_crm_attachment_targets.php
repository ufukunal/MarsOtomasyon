<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

if (! class_exists('GuardCrmAttachmentTargets20260906001100', false)) {
    final class GuardCrmAttachmentTargets20260906001100 extends Migration
    {
        public function up(): void
        {
            DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION enforce_crm_attachment_target()
RETURNS trigger AS $$
BEGIN
    IF NEW.attachable_type = 'crm_lead' AND NOT EXISTS (
        SELECT 1 FROM crm_leads
        WHERE crm_leads.company_id = NEW.company_id
          AND crm_leads.id = NEW.attachable_id
    ) THEN
        RAISE EXCEPTION 'CRM lead attachment target does not belong to company'
            USING ERRCODE = '23503';
    END IF;

    IF NEW.attachable_type = 'crm_opportunity' AND NOT EXISTS (
        SELECT 1 FROM crm_opportunities
        WHERE crm_opportunities.company_id = NEW.company_id
          AND crm_opportunities.id = NEW.attachable_id
    ) THEN
        RAISE EXCEPTION 'CRM opportunity attachment target does not belong to company'
            USING ERRCODE = '23503';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE CONSTRAINT TRIGGER attachments_crm_target_guard
AFTER INSERT OR UPDATE OF company_id, attachable_type, attachable_id ON attachments
DEFERRABLE INITIALLY IMMEDIATE
FOR EACH ROW
EXECUTE FUNCTION enforce_crm_attachment_target();
SQL);
        }

        public function down(): void
        {
            DB::unprepared('DROP TRIGGER IF EXISTS attachments_crm_target_guard ON attachments');
            DB::unprepared('DROP FUNCTION IF EXISTS enforce_crm_attachment_target()');
        }
    }
}

return new GuardCrmAttachmentTargets20260906001100;
