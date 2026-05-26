<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Make the (company_id, document_type, official_number) uniqueness partial:
 * only enforce it when deleted_at IS NULL.
 *
 * Without this, soft-deleting a doc keeps its (company, type, number) row in
 * place and the next issue of the same number (the whole point of the recycle
 * feature) hits a UNIQUE constraint violation. PostgreSQL + SQLite both
 * support partial unique indexes; the SQL is identical for our purposes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropUnique('documents_unique_official_number_per_company_type');
        });

        DB::statement(
            'CREATE UNIQUE INDEX documents_unique_official_number_alive '
            .'ON documents (company_id, document_type, official_number) '
            .'WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS documents_unique_official_number_alive');

        Schema::table('documents', function (Blueprint $table) {
            $table->unique(
                ['company_id', 'document_type', 'official_number'],
                'documents_unique_official_number_per_company_type'
            );
        });
    }
};
