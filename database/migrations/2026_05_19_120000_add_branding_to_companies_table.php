<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('logo_path')->nullable()->after('email');
            $table->string('stamp_path')->nullable()->after('logo_path');
            $table->string('signature_path')->nullable()->after('stamp_path');
            $table->string('brand_primary', 7)->default('#1a3a5c')->after('signature_path');
            $table->string('brand_secondary', 7)->default('#f0f4f8')->after('brand_primary');
            $table->string('brand_accent', 7)->default('#16427a')->after('brand_secondary');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'logo_path',
                'stamp_path',
                'signature_path',
                'brand_primary',
                'brand_secondary',
                'brand_accent',
            ]);
        });
    }
};
