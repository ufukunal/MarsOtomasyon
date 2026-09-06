<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_leads', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('owner_user_id')->nullable();
            $table->unsignedBigInteger('converted_account_id')->nullable();
            $table->string('name', 191);
            $table->string('company_name', 191)->nullable();
            $table->string('email', 191)->nullable();
            $table->string('phone', 64)->nullable();
            $table->string('status', 32)->default('open');
            $table->timestampTz('converted_at')->nullable();
            $table->timestampsTz();

            $table->index(['company_id', 'status', 'owner_user_id'], 'crm_leads_scope_index');
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('owner_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('converted_account_id')->references('id')->on('accounts')->nullOnDelete();
        });

        Schema::create('crm_opportunities', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('account_id')->nullable();
            $table->unsignedBigInteger('owner_user_id')->nullable();
            $table->unsignedBigInteger('quote_id')->nullable();
            $table->string('name', 191);
            $table->string('stage', 64)->default('new');
            $table->decimal('expected_value', 20, 6)->nullable();
            $table->char('currency_code', 3)->nullable();
            $table->date('expected_close_date')->nullable();
            $table->string('status', 32)->default('open');
            $table->timestampsTz();

            $table->index(['company_id', 'stage', 'owner_user_id'], 'crm_opportunities_scope_index');
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('lead_id')->references('id')->on('crm_leads')->nullOnDelete();
            $table->foreign('account_id')->references('id')->on('accounts')->nullOnDelete();
            $table->foreign('owner_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('quote_id')->references('id')->on('quotes')->nullOnDelete();
        });

        Schema::create('crm_opportunity_stage_history', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('opportunity_id');
            $table->string('from_stage', 64)->nullable();
            $table->string('to_stage', 64);
            $table->unsignedBigInteger('changed_by_user_id')->nullable();
            $table->timestampTz('changed_at');
            $table->timestampsTz();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('opportunity_id')->references('id')->on('crm_opportunities')->cascadeOnDelete();
            $table->foreign('changed_by_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('crm_activities', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('opportunity_id')->nullable();
            $table->unsignedBigInteger('owner_user_id')->nullable();
            $table->string('activity_type', 64);
            $table->string('subject', 191);
            $table->text('note')->nullable();
            $table->timestampTz('due_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();

            $table->index(['company_id', 'owner_user_id', 'due_at'], 'crm_activities_due_index');
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('lead_id')->references('id')->on('crm_leads')->cascadeOnDelete();
            $table->foreign('opportunity_id')->references('id')->on('crm_opportunities')->cascadeOnDelete();
            $table->foreign('owner_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_activities');
        Schema::dropIfExists('crm_opportunity_stage_history');
        Schema::dropIfExists('crm_opportunities');
        Schema::dropIfExists('crm_leads');
    }
};
