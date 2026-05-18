<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'receipt_document_id')) {
                $table->foreignId('receipt_document_id')
                    ->nullable()
                    ->after('company_id')
                    ->constrained('documents')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('payments', 'currency')) {
                $table->string('currency', 3)->default('MYR')->after('unallocated_amount');
            }

            if (! Schema::hasColumn('payments', 'fx_rate')) {
                $table->decimal('fx_rate', 18, 8)->nullable()->after('currency');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'fx_rate')) {
                $table->dropColumn('fx_rate');
            }

            if (Schema::hasColumn('payments', 'currency')) {
                $table->dropColumn('currency');
            }

            if (Schema::hasColumn('payments', 'receipt_document_id')) {
                $table->dropConstrainedForeignId('receipt_document_id');
            }
        });
    }
};
