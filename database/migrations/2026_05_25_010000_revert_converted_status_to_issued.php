<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Workflow redesign: drop the "convert" model.
     *
     * Previously, generating a child doc (e.g. invoice from quotation) marked
     * the source as status='converted', killing it. The new "derive" model
     * keeps the source 'issued' and just links the child via the existing
     * converted_from_id column (semantically: derived_from_id).
     *
     * This migration flips any pre-existing 'converted' rows back to 'issued'
     * so they're usable again. converted_from_id stays intact — it still
     * carries the ancestry link.
     */
    public function up(): void
    {
        DB::table('documents')
            ->where('status', 'converted')
            ->update(['status' => 'issued']);
    }

    public function down(): void
    {
        // One-way migration. The legacy "mark source converted" behaviour is
        // gone; we don't try to reverse-detect which docs used to be that
        // status. Existing converted_from_id links are unchanged.
    }
};
