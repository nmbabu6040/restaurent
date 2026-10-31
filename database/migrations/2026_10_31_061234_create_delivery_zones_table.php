<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name_en', 100);
            $table->string('name_bn', 100)->nullable();
            $table->decimal('fee', 8, 2)->default(0);
            $table->decimal('min_order', 8, 2)->nullable();
            $table->decimal('free_over', 8, 2)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('delivery_zone_id')->nullable()->after('address')->constrained('delivery_zones')->nullOnDelete();
            $table->string('zone_en', 100)->nullable()->after('delivery_zone_id');
            $table->string('zone_bn', 100)->nullable()->after('zone_en');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('delivery_zone_id');
            $table->dropColumn(['zone_en', 'zone_bn']);
        });

        Schema::dropIfExists('delivery_zones');
    }
};
