<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->string('original_name');
            $table->string('storage_path');
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('caption')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('include_in_pdf')->default(true);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'document_id']);
            $table->index(['document_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_attachments');
    }
};
