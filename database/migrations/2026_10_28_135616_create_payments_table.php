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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('gateway', 20);
            $table->string('tran_id', 40)->unique();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('BDT');
            $table->string('status', 20)->default('initiated');
            $table->string('val_id', 100)->nullable();
            $table->string('bank_tran_id', 100)->nullable();
            $table->string('card_type', 60)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
