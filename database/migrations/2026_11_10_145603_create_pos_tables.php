<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dining_tables', function (Blueprint $table) {
            $table->id();
            $table->string('name', 30)->unique();
            $table->string('section', 40)->nullable();
            $table->unsignedTinyInteger('seats')->default(4);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('opened_at');
            $table->decimal('opening_cash', 10, 2)->default(0);
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->decimal('expected_cash', 10, 2)->nullable();
            $table->decimal('counted_cash', 10, 2)->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->index('closed_at');
        });

        Schema::create('order_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('method', 12);
            $table->decimal('amount', 10, 2);                 // applied to the bill (negative when a bill is voided)
            $table->decimal('tendered', 10, 2)->nullable();   // cash handed over; change = tendered - amount
            $table->string('reference', 60)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cash_session_id')->nullable()->constrained('cash_sessions')->nullOnDelete();
            $table->timestamps();

            $table->index('order_id');
            $table->index(['method', 'created_at']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('source', 10)->default('online')->after('reference');
            $table->foreignId('dining_table_id')->nullable()->after('delivery_zone_id')->constrained('dining_tables')->nullOnDelete();
            $table->string('table_name', 30)->nullable()->after('dining_table_id');
            $table->unsignedTinyInteger('guests')->nullable()->after('table_name');
            $table->foreignId('user_id')->nullable()->after('guests')->constrained('users')->nullOnDelete();
            $table->decimal('discount_percent', 5, 2)->nullable()->after('discount');
            $table->string('discount_reason', 120)->nullable()->after('discount_percent');
            $table->decimal('service_charge', 10, 2)->default(0)->after('discount_reason');

            $table->index(['source', 'created_at']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedSmallInteger('sent_qty')->default(0)->after('quantity');
            $table->string('line_key', 80)->nullable()->after('sent_qty');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', fn(Blueprint $t) => $t->dropColumn(['sent_qty', 'line_key']));

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['source', 'created_at']);
            $table->dropConstrainedForeignId('dining_table_id');
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['source', 'table_name', 'guests', 'discount_percent', 'discount_reason', 'service_charge']);
        });

        Schema::dropIfExists('order_payments');
        Schema::dropIfExists('cash_sessions');
        Schema::dropIfExists('dining_tables');
    }
};
