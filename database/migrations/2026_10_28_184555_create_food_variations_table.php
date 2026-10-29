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
        Schema::create('food_variations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')->constrained('menu_items')->cascadeOnDelete();
            $table->string('name_en', 100);
            $table->string('name_bn', 100)->nullable();
            $table->decimal('price', 10, 2);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['menu_item_id', 'is_active', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('food_variations');
    }
};
