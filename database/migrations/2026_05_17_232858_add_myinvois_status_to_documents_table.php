<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('myinvois_status')->nullable()->after('status')
                ->comment('Future MyInvois: null, pending, submitted, valid, cancelled');
            $table->string('myinvois_uuid')->nullable()->after('myinvois_status');
            $table->string('myinvois_submission_uid')->nullable()->after('myinvois_uuid');
            $table->timestamp('myinvois_validated_at')->nullable()->after('myinvois_submission_uid');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn([
                'myinvois_status', 'myinvois_uuid',
                'myinvois_submission_uid', 'myinvois_validated_at',
            ]);
        });
    }
};
