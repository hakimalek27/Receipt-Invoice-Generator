<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description');
            $table->decimal('quantity', 18, 4)->default(1);
            $table->string('uom', 20)->nullable()->default('unit');
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->decimal('discount', 18, 2)->default(0);
            $table->decimal('line_total', 18, 2)->default(0);
            $table->string('tax_type')->nullable();
            $table->decimal('tax_rate', 8, 2)->nullable();
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->string('classification_code')->nullable();
            $table->string('tax_exemption_reason')->nullable();
            $table->integer('sort_order')->default(0);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['document_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_items');
    }
};
