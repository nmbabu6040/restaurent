<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('ingredients', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('unit', 10);
            $table->decimal('stock', 12, 3)->default(0);
            $table->decimal('reorder_level', 12, 3)->default(0);
            $table->decimal('unit_cost', 12, 4)->default(0);
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('notes', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['is_active', 'name']);
        });

        Schema::create('recipe_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')->constrained('menu_items')->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained('ingredients')->restrictOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->timestamps();

            $table->unique(['menu_item_id', 'ingredient_id']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingredient_id')->constrained('ingredients')->cascadeOnDelete();
            $table->string('type', 12); // purchase, sale, waste, adjustment, return
            $table->decimal('quantity', 12, 3); // signed: plus adds stock, minus removes it
            $table->decimal('unit_cost', 12, 4)->nullable();
            $table->decimal('balance_after', 12, 3);
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 200)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['ingredient_id', 'created_at']);
            $table->index(['type', 'created_at']);
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->date('expense_date');
            $table->string('category', 30);
            $table->decimal('amount', 12, 2);
            $table->string('description', 255)->nullable();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('expense_date');
            $table->index('category');
        });

        Schema::table('menu_items', function (Blueprint $table) {
            $table->string('stock_mode', 10)->default('none')->after('is_special');
            $table->unsignedInteger('stock_qty')->default(0)->after('stock_mode');
            $table->decimal('cost_price', 10, 2)->default(0)->after('stock_qty');
        });

        Schema::table('food_variations', function (Blueprint $table) {
            $table->decimal('portion_factor', 5, 2)->default(1)->after('price');
            $table->decimal('cost_price', 10, 2)->nullable()->after('portion_factor');
        });

        Schema::table('food_addons', function (Blueprint $table) {
            $table->decimal('cost_price', 10, 2)->default(0)->after('price');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('unit_cost', 10, 2)->default(0)->after('unit_price');
            $table->json('consumed')->nullable()->after('line_total');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', fn(Blueprint $t) => $t->dropColumn(['unit_cost', 'consumed']));
        Schema::table('food_addons', fn(Blueprint $t) => $t->dropColumn('cost_price'));
        Schema::table('food_variations', fn(Blueprint $t) => $t->dropColumn(['portion_factor', 'cost_price']));
        Schema::table('menu_items', fn(Blueprint $t) => $t->dropColumn(['stock_mode', 'stock_qty', 'cost_price']));

        Schema::dropIfExists('expenses');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('recipe_lines');
        Schema::dropIfExists('ingredients');
        Schema::dropIfExists('suppliers');
    }
};
