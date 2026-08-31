<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('b2b_users', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id');
            $table->foreignId('account_id');
            $table->string('name', 160);
            $table->string('email', 255);
            $table->string('password');
            $table->string('status', 16)->default('active');
            $table->timestampTz('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestampsTz();

            $table->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
            $table->foreign(['company_id', 'account_id'])
                ->references(['company_id', 'id'])
                ->on('accounts')
                ->restrictOnDelete();
            $table->index(['company_id', 'account_id', 'status']);
        });

        DB::statement('CREATE UNIQUE INDEX b2b_users_company_email_lower_unique ON b2b_users (company_id, lower(email))');
        DB::statement("ALTER TABLE b2b_users ADD CONSTRAINT b2b_users_status_check CHECK (status IN ('active', 'inactive'))");
        DB::statement("ALTER TABLE b2b_users ADD CONSTRAINT b2b_users_public_id_shape_check CHECK (public_id ~ '^[0-9A-HJKMNP-TV-Z]{26}$')");
        DB::statement('ALTER TABLE b2b_users ADD CONSTRAINT b2b_users_name_not_blank_check CHECK (char_length(btrim(name)) > 0)');
        DB::statement('ALTER TABLE b2b_users ADD CONSTRAINT b2b_users_email_not_blank_check CHECK (char_length(btrim(email)) > 0)');

        DB::statement(<<<'SQL'
CREATE OR REPLACE FUNCTION prevent_b2b_user_public_id_change() RETURNS trigger AS $$
BEGIN
    IF NEW.public_id IS DISTINCT FROM OLD.public_id THEN
        RAISE EXCEPTION 'b2b_users.public_id is immutable';
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql
SQL);
        DB::statement('CREATE TRIGGER b2b_users_public_id_immutable BEFORE UPDATE ON b2b_users FOR EACH ROW EXECUTE FUNCTION prevent_b2b_user_public_id_change()');
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS b2b_users_public_id_immutable ON b2b_users');
        DB::statement('DROP FUNCTION IF EXISTS prevent_b2b_user_public_id_change()');
        Schema::dropIfExists('b2b_users');
    }
};
