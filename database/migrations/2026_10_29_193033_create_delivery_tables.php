<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('name', 100);
            $table->string('phone', 20);
            $table->string('vehicle', 12)->default('bike');
            $table->string('vehicle_number', 30)->nullable();
            $table->decimal('fee_per_delivery', 8, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_online')->default(false);
            $table->string('notes', 500)->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['is_active', 'is_online']);
        });

        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained('orders')->cascadeOnDelete();
            $table->foreignId('rider_id')->constrained('riders')->restrictOnDelete();
            $table->string('status', 12)->default('assigned');
            $table->decimal('rider_fee', 8, 2)->default(0);
            $table->decimal('cash_collected', 10, 2)->default(0);
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->string('failed_reason', 255)->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->foreignId('settled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['rider_id', 'status']);
            $table->index(['status', 'delivered_at']);
            $table->index('settled_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliveries');
        Schema::dropIfExists('riders');
    }
};
