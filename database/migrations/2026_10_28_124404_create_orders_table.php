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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 14)->unique();
            $table->string('token', 40)->unique();
            $table->string('type', 20);
            $table->string('name', 100);
            $table->string('phone', 20);
            $table->string('email', 150)->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->string('payment_method', 20)->default('cod');
            $table->string('payment_status', 20)->default('unpaid');
            $table->string('status', 20)->default('pending');
            $table->timestamp('status_changed_at')->nullable();
            $table->string('locale', 5)->default('en');
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
