<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('product_line', 32)->nullable()->after('terms');
            $table->boolean('include_arabic_salutation')->default(false)->after('product_line');
        });

        Schema::table('document_items', function (Blueprint $table) {
            $table->string('section_header', 255)->nullable()->after('description');
            $table->string('image_url', 500)->nullable()->after('section_header');
            $table->decimal('cost_unit', 12, 2)->nullable()->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('document_items', function (Blueprint $table) {
            $table->dropColumn(['section_header', 'image_url', 'cost_unit']);
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['product_line', 'include_arabic_salutation']);
        });
    }
};
