<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('period')->create('number_series', function (Blueprint $table): void {
            $table->id();
            $table->string('document_type', 40);
            $table->string('prefix', 10);
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('last_number')->default(0);
            $table->unsignedTinyInteger('padding')->default(5);
            $table->timestamps();

            $table->unique(['document_type', 'year'], 'number_series_unique');
        });
    }

    public function down(): void
    {
        Schema::connection('period')->dropIfExists('number_series');
    }
};
