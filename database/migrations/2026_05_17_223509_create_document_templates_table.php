<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('document_type', 50);
            $table->string('paper_size', 10)->default('A4');
            $table->boolean('is_default')->default(false);
            $table->boolean('show_amount_in_words')->default(false);
            $table->string('amount_in_words_locale')->nullable()->default('ms_MY');
            $table->string('amount_in_words_currency', 3)->nullable()->default('MYR');
            $table->string('amount_in_words_zero_sen_style')->nullable()->default('SAHAJA');
            $table->string('amount_in_words_label')->nullable();
            $table->string('amount_in_words_position')->nullable();
            $table->boolean('is_active')->default(true);
            $table->jsonb('layout_config')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'document_type', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_templates');
    }
};
