<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('space_photos', function (Blueprint $table) {
            $table->id();
            $table->string('image_path');
            $table->string('caption_en', 150)->nullable();
            $table->string('caption_bn', 150)->nullable();
            $table->string('alt_en', 200);
            $table->string('alt_bn', 200)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('space_photos');
    }
};
