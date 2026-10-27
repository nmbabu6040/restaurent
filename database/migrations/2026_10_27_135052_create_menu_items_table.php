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
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_category_id')->constrained('menu_categories')->restrictOnDelete();
            $table->string('name_en');
            $table->string('name_bn')->nullable();
            $table->string('slug')->unique();
            $table->text('description_en')->nullable();
            $table->text('description_bn')->nullable();
            $table->text('ingredients_en')->nullable();
            $table->text('ingredients_bn')->nullable();
            $table->text('allergens_en')->nullable();
            $table->text('allergens_bn')->nullable();
            $table->decimal('price', 10, 2);
            $table->string('image_path')->nullable();
            $table->json('dietary')->nullable();
            $table->boolean('is_available')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_special')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['menu_category_id', 'is_available']);
            $table->index('is_featured');
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
