<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('numbering_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 50);
            $table->string('prefix')->nullable();
            $table->string('suffix')->nullable();
            $table->string('separator')->nullable()->default('-');
            $table->string('year_token')->default('{YYYY}');
            $table->integer('sequence_padding')->default(5);
            $table->string('reset_policy')->default('yearly');
            $table->jsonb('scope_dimensions')->nullable();
            $table->timestamp('active_from')->nullable();
            $table->timestamp('active_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'document_type', 'is_active'],
                'num_policies_unique_active_per_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('numbering_policies');
    }
};
