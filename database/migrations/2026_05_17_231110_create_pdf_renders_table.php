<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdf_renders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->integer('version')->default(1);
            $table->string('file_path');
            $table->bigInteger('file_size')->nullable();
            $table->string('sha256', 64)->nullable();
            $table->integer('page_count')->default(1);
            $table->string('paper_size', 10)->default('A4');
            $table->string('template_used')->nullable();
            $table->boolean('is_current')->default(true);
            $table->timestamp('rendered_at')->useCurrent();
            $table->timestamps();

            $table->unique(['document_id', 'version']);
            $table->index(['document_id', 'is_current']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdf_renders');
    }
};
