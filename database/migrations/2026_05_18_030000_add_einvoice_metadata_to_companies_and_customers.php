<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('tin')->nullable()->after('registration_number');
            $table->string('sst_registration_number')->nullable()->after('tin');
            $table->string('msic_code', 10)->nullable()->after('sst_registration_number');
            $table->string('business_activity_description')->nullable()->after('msic_code');
            $table->string('address_line_2')->nullable()->after('address');
            $table->string('city')->nullable()->after('address_line_2');
            $table->string('state')->nullable()->after('city');
            $table->string('postcode', 10)->nullable()->after('state');
            $table->string('country', 3)->nullable()->default('MY')->after('postcode');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('brn_registration_number')->nullable()->after('tax_identifier');
            $table->string('sst_registration_number')->nullable()->after('brn_registration_number');
            $table->string('msic_code', 10)->nullable()->after('sst_registration_number');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'brn_registration_number',
                'sst_registration_number',
                'msic_code',
            ]);
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'tin',
                'sst_registration_number',
                'msic_code',
                'business_activity_description',
                'address_line_2',
                'city',
                'state',
                'postcode',
                'country',
            ]);
        });
    }
};
