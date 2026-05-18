<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sequence_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 50);
            $table->integer('year');
            $table->bigInteger('current_sequence')->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'document_type', 'year'],
                'seq_counters_unique_per_company_type_year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sequence_counters');
    }
};
