<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_invoice_finalized_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('sales_invoice_id');
            $table->unsignedBigInteger('file_asset_id');
            $table->string('renderer_version', 64);
            $table->char('source_fingerprint', 64);
            $table->char('pdf_sha256', 64);
            $table->timestampTz('generated_at');
            $table->timestampsTz();

            $table->unique(['company_id', 'id'], 'sales_invoice_finalized_documents_company_id_id_unique');
            $table->unique(['company_id', 'sales_invoice_id', 'renderer_version'], 'sales_invoice_finalized_documents_version_unique');
            $table->unique(['company_id', 'file_asset_id'], 'sales_invoice_finalized_documents_file_asset_unique');
            $table->foreign(['company_id', 'sales_invoice_id'], 'sales_invoice_finalized_documents_invoice_fk')
                ->references(['company_id', 'id'])->on('sales_invoices')->restrictOnDelete();
            $table->foreign(['file_asset_id', 'company_id'], 'sales_invoice_finalized_documents_file_asset_fk')
                ->references(['id', 'company_id'])->on('file_assets')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE sales_invoice_finalized_documents ADD CONSTRAINT sales_invoice_finalized_documents_renderer_check CHECK (renderer_version = lower(btrim(renderer_version)) AND renderer_version ~ '^[a-z0-9]+(?:[._-][a-z0-9]+)*$')");
        DB::statement("ALTER TABLE sales_invoice_finalized_documents ADD CONSTRAINT sales_invoice_finalized_documents_source_fingerprint_check CHECK (source_fingerprint ~ '^[0-9a-f]{64}$')");
        DB::statement("ALTER TABLE sales_invoice_finalized_documents ADD CONSTRAINT sales_invoice_finalized_documents_pdf_sha256_check CHECK (pdf_sha256 ~ '^[0-9a-f]{64}$')");

        Schema::create('sales_invoice_e_document_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('sales_invoice_id');
            $table->string('document_type', 32);
            $table->string('event_type', 32);
            $table->string('provider_key', 64)->nullable();
            $table->string('external_document_id', 160)->nullable();
            $table->char('payload_sha256', 64)->nullable();
            $table->timestampTz('occurred_at');
            $table->timestampsTz();

            $table->unique(['company_id', 'id'], 'sales_invoice_e_document_events_company_id_id_unique');
            $table->foreign(['company_id', 'sales_invoice_id'], 'sales_invoice_e_document_events_invoice_fk')
                ->references(['company_id', 'id'])->on('sales_invoices')->restrictOnDelete();
            $table->index(['company_id', 'sales_invoice_id', 'document_type', 'id'], 'sales_invoice_e_document_events_stream_index');
        });

        DB::statement("ALTER TABLE sales_invoice_e_document_events ADD CONSTRAINT sales_invoice_e_document_events_type_check CHECK (document_type IN ('e_invoice', 'e_archive'))");
        DB::statement("ALTER TABLE sales_invoice_e_document_events ADD CONSTRAINT sales_invoice_e_document_events_event_check CHECK (event_type IN ('prepared', 'submitted', 'accepted', 'rejected', 'cancelled'))");
        DB::statement("ALTER TABLE sales_invoice_e_document_events ADD CONSTRAINT sales_invoice_e_document_events_provider_check CHECK (provider_key IS NULL OR (provider_key = lower(btrim(provider_key)) AND provider_key ~ '^[a-z0-9]+(?:[._-][a-z0-9]+)*$'))");
        DB::statement("ALTER TABLE sales_invoice_e_document_events ADD CONSTRAINT sales_invoice_e_document_events_payload_check CHECK (payload_sha256 IS NULL OR payload_sha256 ~ '^[0-9a-f]{64}$')");

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_sales_invoice_finalized_document_insert()
RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    invoice_status text;
    invoice_finalized_at timestamptz;
    asset_mime text;
    asset_extension text;
    asset_sha text;
    asset_archived timestamptz;
BEGIN
    SELECT status, finalized_at
      INTO invoice_status, invoice_finalized_at
      FROM sales_invoices
     WHERE company_id = NEW.company_id
       AND id = NEW.sales_invoice_id;

    IF invoice_status IS NULL
       OR invoice_status NOT IN ('finalized', 'cancelled')
       OR invoice_finalized_at IS NULL THEN
        RAISE EXCEPTION 'finalized invoice PDF requires a finalized sales invoice' USING ERRCODE = '23514';
    END IF;

    SELECT mime_type, client_extension, sha256, archived_at
      INTO asset_mime, asset_extension, asset_sha, asset_archived
      FROM file_assets
     WHERE company_id = NEW.company_id
       AND id = NEW.file_asset_id;

    IF asset_mime IS DISTINCT FROM 'application/pdf'
       OR lower(COALESCE(asset_extension, '')) <> 'pdf'
       OR asset_sha IS DISTINCT FROM NEW.pdf_sha256
       OR asset_archived IS NOT NULL THEN
        RAISE EXCEPTION 'finalized invoice PDF file asset metadata is invalid' USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$;

CREATE TRIGGER sales_invoice_finalized_documents_insert_guard
BEFORE INSERT ON sales_invoice_finalized_documents
FOR EACH ROW EXECUTE FUNCTION mars_guard_sales_invoice_finalized_document_insert();

CREATE OR REPLACE FUNCTION mars_prevent_sales_invoice_finalized_document_mutation()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    RAISE EXCEPTION 'finalized sales invoice document is immutable' USING ERRCODE = '55000';
END;
$$;

CREATE TRIGGER sales_invoice_finalized_documents_immutable
BEFORE UPDATE OR DELETE ON sales_invoice_finalized_documents
FOR EACH ROW EXECUTE FUNCTION mars_prevent_sales_invoice_finalized_document_mutation();

CREATE OR REPLACE FUNCTION mars_guard_finalized_pdf_file_asset_mutation()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM quote_finalized_documents qfd
         WHERE qfd.company_id = OLD.company_id AND qfd.file_asset_id = OLD.id
    ) OR EXISTS (
        SELECT 1 FROM sales_invoice_finalized_documents sifd
         WHERE sifd.company_id = OLD.company_id AND sifd.file_asset_id = OLD.id
    ) THEN
        RAISE EXCEPTION 'file asset belongs to an immutable finalized document' USING ERRCODE = '55000';
    END IF;

    RETURN COALESCE(NEW, OLD);
END;
$$;

CREATE OR REPLACE FUNCTION mars_guard_sales_invoice_e_document_event_insert()
RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    invoice_status text;
    previous_event text;
    previous_provider text;
BEGIN
    SELECT status
      INTO invoice_status
      FROM sales_invoices
     WHERE company_id = NEW.company_id
       AND id = NEW.sales_invoice_id
     FOR UPDATE;

    IF invoice_status IS NULL OR invoice_status NOT IN ('finalized', 'cancelled') THEN
        RAISE EXCEPTION 'e-document lifecycle requires a finalized sales invoice' USING ERRCODE = '23514';
    END IF;

    SELECT event_type, provider_key
      INTO previous_event, previous_provider
      FROM sales_invoice_e_document_events
     WHERE company_id = NEW.company_id
       AND sales_invoice_id = NEW.sales_invoice_id
       AND document_type = NEW.document_type
     ORDER BY id DESC
     LIMIT 1;

    IF NEW.event_type = 'prepared' THEN
        IF previous_event IS NOT NULL
           OR NEW.provider_key IS NOT NULL
           OR NEW.external_document_id IS NOT NULL
           OR NEW.payload_sha256 IS NOT NULL THEN
            RAISE EXCEPTION 'prepared e-document event must start an empty provider-neutral stream' USING ERRCODE = '23514';
        END IF;
        IF invoice_status = 'cancelled' THEN
            RAISE EXCEPTION 'cancelled invoice cannot start a new e-document stream' USING ERRCODE = '23514';
        END IF;
    ELSIF NEW.event_type = 'submitted' THEN
        IF invoice_status = 'cancelled'
           OR previous_event IS NULL
           OR previous_event NOT IN ('prepared', 'rejected')
           OR NEW.provider_key IS NULL
           OR NEW.payload_sha256 IS NULL THEN
            RAISE EXCEPTION 'submitted e-document event violates lifecycle contract' USING ERRCODE = '23514';
        END IF;
    ELSIF NEW.event_type IN ('accepted', 'rejected') THEN
        IF invoice_status = 'cancelled'
           OR previous_event IS DISTINCT FROM 'submitted'
           OR NEW.provider_key IS NULL
           OR previous_provider IS DISTINCT FROM NEW.provider_key THEN
            RAISE EXCEPTION 'provider result e-document event violates lifecycle contract' USING ERRCODE = '23514';
        END IF;
    ELSIF NEW.event_type = 'cancelled' THEN
        IF invoice_status <> 'cancelled'
           OR previous_event IS NULL
           OR previous_event = 'cancelled'
           OR (previous_provider IS NOT NULL AND NEW.provider_key IS DISTINCT FROM previous_provider) THEN
            RAISE EXCEPTION 'cancelled e-document event violates lifecycle contract' USING ERRCODE = '23514';
        END IF;
    END IF;

    RETURN NEW;
END;
$$;

CREATE TRIGGER sales_invoice_e_document_events_insert_guard
BEFORE INSERT ON sales_invoice_e_document_events
FOR EACH ROW EXECUTE FUNCTION mars_guard_sales_invoice_e_document_event_insert();

CREATE OR REPLACE FUNCTION mars_prevent_sales_invoice_e_document_event_mutation()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    RAISE EXCEPTION 'sales invoice e-document event is append-only' USING ERRCODE = '55000';
END;
$$;

CREATE TRIGGER sales_invoice_e_document_events_immutable
BEFORE UPDATE OR DELETE ON sales_invoice_e_document_events
FOR EACH ROW EXECUTE FUNCTION mars_prevent_sales_invoice_e_document_event_mutation();
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS sales_invoice_e_document_events_immutable ON sales_invoice_e_document_events');
        DB::statement('DROP TRIGGER IF EXISTS sales_invoice_e_document_events_insert_guard ON sales_invoice_e_document_events');
        DB::statement('DROP FUNCTION IF EXISTS mars_prevent_sales_invoice_e_document_event_mutation()');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_sales_invoice_e_document_event_insert()');
        DB::statement('DROP TRIGGER IF EXISTS sales_invoice_finalized_documents_immutable ON sales_invoice_finalized_documents');
        DB::statement('DROP TRIGGER IF EXISTS sales_invoice_finalized_documents_insert_guard ON sales_invoice_finalized_documents');
        DB::statement('DROP FUNCTION IF EXISTS mars_prevent_sales_invoice_finalized_document_mutation()');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_sales_invoice_finalized_document_insert()');

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_finalized_pdf_file_asset_mutation()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF EXISTS (
        SELECT 1
          FROM quote_finalized_documents qfd
         WHERE qfd.company_id = OLD.company_id
           AND qfd.file_asset_id = OLD.id
    ) THEN
        RAISE EXCEPTION 'file asset belongs to an immutable finalized quote PDF' USING ERRCODE = '55000';
    END IF;

    RETURN COALESCE(NEW, OLD);
END;
$$;
SQL);

        Schema::dropIfExists('sales_invoice_e_document_events');
        Schema::dropIfExists('sales_invoice_finalized_documents');
    }
};
