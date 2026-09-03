<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_installation_guides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('product_id');
            $table->string('title', 160);
            $table->text('intro')->nullable();
            $table->jsonb('steps')->default(DB::raw("'[]'::jsonb"));
            $table->jsonb('warnings')->default(DB::raw("'[]'::jsonb"));
            $table->jsonb('tools')->default(DB::raw("'[]'::jsonb"));
            $table->jsonb('parts')->default(DB::raw("'[]'::jsonb"));
            $table->jsonb('image_product_file_ids')->default(DB::raw("'[]'::jsonb"));
            $table->unsignedBigInteger('content_revision')->default(1);
            $table->timestampsTz();

            $table->unique(['company_id', 'product_id'], 'product_installation_guides_product_unique');
            $table->unique(['company_id', 'id'], 'product_installation_guides_company_id_unique');
            $table->foreign(['company_id', 'product_id'])
                ->references(['company_id', 'id'])->on('products')->restrictOnDelete();
        });

        foreach (['steps', 'warnings', 'tools', 'parts', 'image_product_file_ids'] as $column) {
            DB::statement("ALTER TABLE product_installation_guides ADD CONSTRAINT product_installation_guides_{$column}_array_check CHECK (jsonb_typeof({$column}) = 'array')");
        }
        DB::statement("ALTER TABLE product_installation_guides ADD CONSTRAINT product_installation_guides_title_check CHECK (btrim(title) <> '')");
        DB::statement('ALTER TABLE product_installation_guides ADD CONSTRAINT product_installation_guides_revision_check CHECK (content_revision > 0)');

        Schema::create('product_installation_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('guide_id');
            $table->unsignedBigInteger('file_asset_id');
            $table->unsignedInteger('version');
            $table->string('renderer_version', 64);
            $table->jsonb('snapshot');
            $table->char('source_fingerprint', 64);
            $table->char('pdf_sha256', 64);
            $table->timestampTz('generated_at');
            $table->timestampsTz();

            $table->unique(['company_id', 'product_id', 'version'], 'product_installation_documents_version_unique');
            $table->unique(['company_id', 'product_id', 'renderer_version', 'source_fingerprint'], 'product_installation_documents_source_unique');
            $table->unique(['company_id', 'file_asset_id'], 'product_installation_documents_file_unique');
            $table->foreign(['company_id', 'product_id'])
                ->references(['company_id', 'id'])->on('products')->restrictOnDelete();
            $table->foreign(['company_id', 'guide_id'])
                ->references(['company_id', 'id'])->on('product_installation_guides')->restrictOnDelete();
            $table->foreign(['file_asset_id', 'company_id'], 'product_installation_documents_file_fk')
                ->references(['id', 'company_id'])->on('file_assets')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE product_installation_documents ADD CONSTRAINT product_installation_documents_version_check CHECK (version > 0)');
        DB::statement("ALTER TABLE product_installation_documents ADD CONSTRAINT product_installation_documents_renderer_check CHECK (renderer_version = lower(btrim(renderer_version)) AND renderer_version ~ '^[a-z0-9]+(?:[._-][a-z0-9]+)*$')");
        DB::statement("ALTER TABLE product_installation_documents ADD CONSTRAINT product_installation_documents_source_check CHECK (source_fingerprint ~ '^[0-9a-f]{64}$')");
        DB::statement("ALTER TABLE product_installation_documents ADD CONSTRAINT product_installation_documents_pdf_check CHECK (pdf_sha256 ~ '^[0-9a-f]{64}$')");
        DB::statement("ALTER TABLE product_installation_documents ADD CONSTRAINT product_installation_documents_snapshot_check CHECK (jsonb_typeof(snapshot) = 'object')");

        DB::statement(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_product_installation_document_insert()
RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    guide_product bigint;
    asset_mime text;
    asset_extension text;
    asset_sha text;
    asset_archived timestamptz;
BEGIN
    SELECT product_id INTO guide_product
      FROM product_installation_guides
     WHERE company_id = NEW.company_id AND id = NEW.guide_id;

    IF guide_product IS DISTINCT FROM NEW.product_id THEN
        RAISE EXCEPTION 'installation guide does not belong to product' USING ERRCODE = '23514';
    END IF;

    SELECT mime_type, client_extension, sha256, archived_at
      INTO asset_mime, asset_extension, asset_sha, asset_archived
      FROM file_assets
     WHERE company_id = NEW.company_id AND id = NEW.file_asset_id;

    IF asset_mime IS DISTINCT FROM 'application/pdf'
       OR lower(COALESCE(asset_extension, '')) <> 'pdf'
       OR asset_sha IS DISTINCT FROM NEW.pdf_sha256
       OR asset_archived IS NOT NULL THEN
        RAISE EXCEPTION 'installation PDF file asset metadata is invalid' USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$
SQL);
        DB::statement('CREATE TRIGGER product_installation_documents_insert_guard BEFORE INSERT ON product_installation_documents FOR EACH ROW EXECUTE FUNCTION mars_guard_product_installation_document_insert()');

        DB::statement(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_prevent_product_installation_document_mutation()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    RAISE EXCEPTION 'published product installation document is immutable' USING ERRCODE = '55000';
END;
$$
SQL);
        DB::statement('CREATE TRIGGER product_installation_documents_immutable BEFORE UPDATE OR DELETE ON product_installation_documents FOR EACH ROW EXECUTE FUNCTION mars_prevent_product_installation_document_mutation()');

        DB::statement(<<<'SQL'
CREATE OR REPLACE FUNCTION mars_guard_product_installation_pdf_asset_mutation()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM product_installation_documents d
         WHERE d.company_id = OLD.company_id AND d.file_asset_id = OLD.id
    ) THEN
        RAISE EXCEPTION 'file asset belongs to an immutable installation PDF' USING ERRCODE = '55000';
    END IF;

    RETURN COALESCE(NEW, OLD);
END;
$$
SQL);
        DB::statement('CREATE TRIGGER file_assets_product_installation_pdf_guard BEFORE UPDATE OR DELETE ON file_assets FOR EACH ROW EXECUTE FUNCTION mars_guard_product_installation_pdf_asset_mutation()');
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS file_assets_product_installation_pdf_guard ON file_assets');
        DB::statement('DROP TRIGGER IF EXISTS product_installation_documents_immutable ON product_installation_documents');
        DB::statement('DROP TRIGGER IF EXISTS product_installation_documents_insert_guard ON product_installation_documents');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_product_installation_pdf_asset_mutation()');
        DB::statement('DROP FUNCTION IF EXISTS mars_prevent_product_installation_document_mutation()');
        DB::statement('DROP FUNCTION IF EXISTS mars_guard_product_installation_document_insert()');
        Schema::dropIfExists('product_installation_documents');
        Schema::dropIfExists('product_installation_guides');
    }
};
