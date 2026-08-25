<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_finalized_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('quote_id');
            $table->unsignedBigInteger('quote_revision_id');
            $table->unsignedBigInteger('file_asset_id');
            $table->string('renderer_version', 64);
            $table->char('source_fingerprint', 64);
            $table->char('pdf_sha256', 64);
            $table->timestampTz('generated_at');
            $table->timestampsTz();

            $table->unique(['company_id', 'id'], 'quote_finalized_documents_company_id_id_unique');
            $table->unique(['company_id', 'quote_id', 'renderer_version'], 'quote_finalized_documents_quote_version_unique');
            $table->unique(['company_id', 'file_asset_id'], 'quote_finalized_documents_file_asset_unique');
            $table->foreign(['company_id', 'quote_id'])
                ->references(['company_id', 'id'])->on('quotes')->restrictOnDelete();
            $table->foreign(['company_id', 'quote_id', 'quote_revision_id'], 'quote_finalized_documents_revision_fk')
                ->references(['company_id', 'quote_id', 'id'])->on('quote_revisions')->restrictOnDelete();
            $table->foreign(['file_asset_id', 'company_id'], 'quote_finalized_documents_file_asset_fk')
                ->references(['id', 'company_id'])->on('file_assets')->restrictOnDelete();
            $table->index(['company_id', 'quote_revision_id'], 'quote_finalized_documents_revision_index');
        });

        DB::statement("ALTER TABLE quote_finalized_documents ADD CONSTRAINT quote_finalized_documents_renderer_version_check CHECK (renderer_version = lower(btrim(renderer_version)) AND renderer_version ~ '^[a-z0-9]+(?:[._-][a-z0-9]+)*$')");
        DB::statement("ALTER TABLE quote_finalized_documents ADD CONSTRAINT quote_finalized_documents_source_fingerprint_check CHECK (source_fingerprint ~ '^[0-9a-f]{64}$')");
        DB::statement("ALTER TABLE quote_finalized_documents ADD CONSTRAINT quote_finalized_documents_pdf_sha256_check CHECK (pdf_sha256 ~ '^[0-9a-f]{64}$')");

        DB::statement(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_quote_finalized_document_insert()
RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    quote_status text;
    selected_revision bigint;
    asset_mime text;
    asset_extension text;
    asset_sha text;
    asset_archived timestamptz;
BEGIN
    SELECT status, selected_revision_id
      INTO quote_status, selected_revision
      FROM quotes
     WHERE company_id = NEW.company_id
       AND id = NEW.quote_id;

    IF quote_status IS NULL
       OR quote_status NOT IN ('approved', 'rejected', 'converted')
       OR selected_revision IS DISTINCT FROM NEW.quote_revision_id THEN
        RAISE EXCEPTION 'finalized PDF requires the selected finalized quote revision' USING ERRCODE = '23514';
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
        RAISE EXCEPTION 'finalized PDF file asset metadata is invalid' USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$
SQL);
        DB::statement('CREATE TRIGGER quote_finalized_documents_insert_guard BEFORE INSERT ON quote_finalized_documents FOR EACH ROW EXECUTE FUNCTION mars_guard_quote_finalized_document_insert()');

        DB::statement(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_prevent_quote_finalized_document_mutation()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    RAISE EXCEPTION 'finalized quote document is immutable' USING ERRCODE = '55000';
END;
$$
SQL);
        DB::statement('CREATE TRIGGER quote_finalized_documents_immutable BEFORE UPDATE OR DELETE ON quote_finalized_documents FOR EACH ROW EXECUTE FUNCTION mars_prevent_quote_finalized_document_mutation()');

        DB::statement(<<<'SQL'
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
$$
SQL);
        DB::statement('CREATE TRIGGER file_assets_finalized_pdf_guard BEFORE UPDATE OR DELETE ON file_assets FOR EACH ROW EXECUTE FUNCTION mars_guard_finalized_pdf_file_asset_mutation()');
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS file_assets_finalized_pdf_guard ON file_assets');
        DB::statement('DROP TRIGGER IF EXISTS quote_finalized_documents_immutable ON quote_finalized_documents');
        DB::statement('DROP TRIGGER IF EXISTS quote_finalized_documents_insert_guard ON quote_finalized_documents');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_finalized_pdf_file_asset_mutation()');
        DB::statement('DROP FUNCTION IF EXISTS mars_prevent_quote_finalized_document_mutation()');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_quote_finalized_document_insert()');
        Schema::dropIfExists('quote_finalized_documents');
    }
};
