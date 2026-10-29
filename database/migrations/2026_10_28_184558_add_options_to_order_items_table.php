<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('variation_en', 100)->nullable()->after('name_bn');
            $table->string('variation_bn', 100)->nullable()->after('variation_en');
            $table->json('addons')->nullable()->after('variation_bn');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['variation_en', 'variation_bn', 'addons']);
        });
    }
};
