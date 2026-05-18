<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('idempotency_keys', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
            $table->unsignedBigInteger('document_id')->nullable()->after('resource_id');
            $table->string('draft_hash', 64)->nullable()->after('document_id');
            $table->string('request_hash', 64)->nullable()->after('draft_hash');
            $table->string('status', 20)->default('processing')->after('request_hash');

            $table->index(['company_id', 'key']);
            $table->unique(
                ['company_id', 'user_id', 'document_id', 'resource_type', 'key'],
                'idempotency_unique_scoped_action_key'
            );
        });
    }

    public function down(): void
    {
        Schema::table('idempotency_keys', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropForeign(['user_id']);
            $table->dropUnique('idempotency_unique_scoped_action_key');
            $table->dropColumn(['company_id', 'user_id', 'document_id', 'draft_hash', 'request_hash', 'status']);
        });
    }
};
