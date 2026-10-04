<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('period')->create('price_lists', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->char('currency', 3)->default('TRY');
            $table->boolean('vat_included')->default(false);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });

        DB::connection('period')->statement(
            'CREATE UNIQUE INDEX price_lists_one_default
             ON price_lists ((1)) WHERE is_default = true'
        );
    }

    public function down(): void
    {
        Schema::connection('period')->dropIfExists('price_lists');
    }
};
