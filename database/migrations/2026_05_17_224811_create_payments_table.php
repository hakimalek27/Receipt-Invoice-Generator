<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('receipt_document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->date('payment_date');
            $table->decimal('amount', 18, 2);
            $table->decimal('unallocated_amount', 18, 2);
            $table->string('currency', 3)->default('MYR');
            $table->decimal('fx_rate', 18, 8)->nullable();
            $table->string('method', 30)->default('bank_transfer');
            $table->string('reference_number')->nullable();
            $table->text('notes')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'payment_date']);
        });

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 18, 2);
            $table->timestamps();

            $table->unique(['payment_id', 'document_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payments');
    }
};
