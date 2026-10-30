<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')->constrained('menu_items')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('name', 100);
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->string('status', 10)->default('pending'); // pending, approved, hidden
            $table->boolean('is_verified')->default(false);
            $table->string('locale', 5)->default('en');
            $table->string('ip_address', 45)->nullable();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'menu_item_id']); // one review per dish per order
            $table->index(['menu_item_id', 'status', 'id']);
            $table->index('status');
        });

        Schema::create('review_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained('reviews')->cascadeOnDelete();
            $table->string('name', 100);
            $table->text('body');
            $table->boolean('is_staff')->default(false);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 10)->default('pending');
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['review_id', 'status']);
            $table->index('status');
        });

        Schema::table('menu_items', function (Blueprint $table) {
            $table->unsignedInteger('reviews_count')->default(0)->after('cost_price');
            $table->decimal('rating_avg', 3, 2)->default(0)->after('reviews_count');
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', fn(Blueprint $t) => $t->dropColumn(['reviews_count', 'rating_avg']));
        Schema::dropIfExists('review_replies');
        Schema::dropIfExists('reviews');
    }
};
