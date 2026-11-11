<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedSmallInteger('ready_qty')->default(0)->after('sent_qty');
            $table->timestamp('sent_at')->nullable()->after('ready_qty');
            $table->timestamp('ready_at')->nullable()->after('sent_at');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->foreignId('dining_table_id')->nullable()->after('party_size')->constrained('dining_tables')->nullOnDelete();
            $table->foreignId('seated_order_id')->nullable()->after('dining_table_id')->constrained('orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('seated_order_id');
            $table->dropConstrainedForeignId('dining_table_id');
        });

        Schema::table('order_items', fn (Blueprint $t) => $t->dropColumn(['ready_qty', 'sent_at', 'ready_at']));
    }
};