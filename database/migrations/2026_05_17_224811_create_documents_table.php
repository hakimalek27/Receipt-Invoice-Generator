<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 50);
            $table->string('status', 20)->default('draft');
            $table->string('official_number')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->date('document_date')->nullable();
            $table->date('due_date')->nullable();
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('discount_total', 18, 2)->default(0);
            $table->decimal('tax_total', 18, 2)->default(0);
            $table->decimal('grand_total', 18, 2)->default(0);
            $table->string('currency', 3)->default('MYR');
            $table->decimal('fx_rate', 18, 8)->nullable();
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->text('internal_notes')->nullable();
            $table->boolean('show_amount_in_words')->default(false);
            $table->string('amount_in_words_locale')->nullable();
            $table->string('amount_in_words_currency', 3)->nullable();
            $table->text('amount_in_words_text')->nullable();
            $table->foreignId('template_version_id')->nullable();
            $table->foreignId('converted_from_id')->nullable();
            $table->string('draft_hash', 64)->nullable();
            $table->jsonb('issuer_snapshot_json')->nullable();
            $table->jsonb('buyer_snapshot_json')->nullable();
            $table->jsonb('bank_snapshot_json')->nullable();
            $table->jsonb('terms_snapshot_json')->nullable();
            $table->jsonb('tax_snapshot_json')->nullable();
            $table->jsonb('currency_fx_snapshot_json')->nullable();
            $table->string('issue_timezone_snapshot')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'document_type', 'status']);
            $table->index(['company_id', 'customer_id']);
            $table->unique(
                ['company_id', 'document_type', 'official_number'],
                'documents_unique_official_number_per_company_type'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
